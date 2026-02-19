<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Shared\Contracts\AtomicIdempotencyInterface;
use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Common\Exceptions\IdempotencyKeyReuseException;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AtomicIdempotencyService implements AtomicIdempotencyInterface
{
    private const TABLE = 'processed_commands';

    private const PAYLOAD_VERSION = 1;

    public function __construct(
        private readonly ConnectionInterface $db
    ) {}

    public function execute(string $idempotencyKey, string $tenantId, string $commandHash, Closure $operation): mixed
    {
        // 1. Start Transaction - The atomic boundary
        $this->db->beginTransaction();

        try {
            // 2. The Atomic Insert ("Insert-or-Die")
            // specific SQL state 23000 (Integrity constraint violation) will be thrown if key exists.
            $this->db->table(self::TABLE)->insert([
                'idempotency_key' => $idempotencyKey,
                'tenant_id' => $tenantId,
                'command_hash' => $commandHash,
                'status' => 'PROCESSING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. If we are here, we WON the race. Execute Domain Logic.
            $result = $operation();

            // 3.1. Serialize with Governance (Payload Versioning)
            // Safety: Handle scalar result types
            $payload = [
                'v' => self::PAYLOAD_VERSION,
                'type' => is_object($result) ? get_class($result) : gettype($result),
                'data' => $result,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ];

            // 4. Mark Completed
            $this->db->table(self::TABLE)
                ->where('idempotency_key', $idempotencyKey)
                ->update([
                    'status' => 'COMPLETED',
                    'response_payload' => json_encode($payload),
                    'updated_at' => now(),
                ]);

            $this->db->commit();

            return $result;

        } catch (QueryException $e) {
            // 5. Handle Collision (Unique Constraint Violation)
            if ($e->getCode() === '23000' || $e->errorInfo[1] === 1062) {
                // Another process has this key.
                // Rolling back cleans up the 'PROCESSING' row if this transaction created it
                // (though in collision case, it failed to create it).
                $this->db->rollBack();

                return $this->handleCollision($idempotencyKey, $commandHash);
            }

            // Other DB error? Rollback and rethrow.
            $this->db->rollBack();
            throw $e;
        } catch (\DomainException $e) {
            // 6. DBRE Mandate: Fail-Safe for Business Logic Errors
            // Explicit Rollback ensures 'PROCESSING' row is REMOVED (Lock Released)
            // This allows the user (or client) to correct input and retry immediately.
            $this->db->rollBack();
            throw $e;
        } catch (Throwable $e) {
            // General Failure
            $this->db->rollBack();
            throw $e;
        }
    }

    private function handleCollision(string $key, string $hash): mixed
    {
        // Read the existing state
        $record = $this->db->table(self::TABLE)
            ->where('idempotency_key', $key)
            // ->lockForUpdate() // Avoid lock here to prevent deadlock with the winner
            ->first();

        if (! $record) {
            // Should not happen if 23000 was thrown, unless it was deleted immediately after.
            throw new ConcurrentRequestException('Idempotency race condition detected, but record vanished.');
        }

        // Security Check: Hash Integrity
        if ($record->command_hash !== $hash) {
            throw new IdempotencyKeyReuseException('Idempotency key reused for different payload.');
        }

        if ($record->status === 'COMPLETED') {
            $payload = json_decode($record->response_payload, true);

            // DBRE Governance: Version Check
            // If the version key is missing, or doesn't match our current version:
            if (! isset($payload['v']) || $payload['v'] !== self::PAYLOAD_VERSION) {
                // Version mismatch (Breaking Change).
                // Invalidate Cache by Deleting Record and Force Client Retry.
                $this->db->table(self::TABLE)->where('idempotency_key', $key)->delete();
                throw new ConcurrentRequestException('Idempotency schema version mismatch. State flushed. Please retry.');
            }

            return $payload['data'];
        }

        if ($record->status === 'PROCESSING') {
            throw new ConcurrentRequestException('Request is currently being processed by another worker.');
        }

        if ($record->status === 'FAILED') {
            throw new \Exception('Previous request failed.');
        }

        return null;
    }
}
