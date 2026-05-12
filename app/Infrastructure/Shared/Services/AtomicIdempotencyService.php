<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Application\Common\Exceptions\ConcurrentRequestException;
use App\Application\Common\Exceptions\IdempotencyKeyReuseException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AtomicIdempotencyService - The "Zombie Reaper" Implementation
 *
 * Implements strict ACID concurrency control with Self-Healing capabilities.
 * Designed to survive infrastructure failures (crashes, power loss) by detecting
 * and recovering stale locks ("Zombies") automatically.
 */
final class AtomicIdempotencyService implements AtomicIdempotencyInterface
{
    private const PAYLOAD_VERSION = 1;

    /**
     * Time-To-Live for a processing lock.
     * If a process takes longer than this without updating, it is considered dead.
     */
    private const LOCK_TTL_SECONDS = 60;

    /**
     * {@inheritDoc}
     */
    public function execute(string $idempotencyKey, string $tenantId, string $commandHash, Closure $operation): mixed
    {
        // 1. PHASE 1: THE GUARD (Atomic Acquisition)
        // Try to obtain lock via Database Unique Constraint.
        // This is safer than Redis availability.
        try {
            DB::table('processed_commands')->insert([
                'idempotency_key' => $idempotencyKey,
                'tenant_id' => $tenantId,
                'command_hash' => $commandHash,
                'status' => 'PROCESSING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Check for Unique Constraint Violation (Duplicate Key - SQLState 23000)
            if ($this->isDuplicateEntryException($e)) {
                return $this->handleExisting($idempotencyKey, $commandHash, $operation);
            }
            throw $e;
        }

        // 2. Lock Acquired - Execute Operation
        return $this->runOperation($idempotencyKey, $operation);
    }

    /**
     * Executes the operation and updates final status.
     * Used by both initial acquisition and zombie recovery flows.
     */
    private function runOperation(string $idempotencyKey, Closure $operation): mixed
    {
        try {
            $result = $operation();

            // 3. Success - Mark Completed and Store Result
            DB::table('processed_commands')
                ->where('idempotency_key', $idempotencyKey)
                ->update([
                    'status' => 'COMPLETED',
                    'response_payload' => json_encode([
                        'v' => self::PAYLOAD_VERSION,
                        'type' => is_object($result) ? get_class($result) : gettype($result),
                        'data' => $result,
                        'meta' => [
                            'timestamp' => now()->toIso8601String(),
                        ],
                    ]),
                    'updated_at' => now(),
                ]);

            return $result;

        } catch (\Throwable $e) {
            // 4. Failure - release the processing lock so corrected input can retry.
            try {
                DB::table('processed_commands')
                    ->where('idempotency_key', $idempotencyKey)
                    ->delete();
            } catch (\Throwable $inner) {
                // Fail silently to preserve original exception logic
            }

            throw $e;
        }
    }

    /**
     * PHASE 2: THE REAPER LOGIC (Recovery & Hostile Takeover)
     */
    private function handleExisting(string $key, string $hash, Closure $operation): mixed
    {
        $record = DB::table('processed_commands')->where('idempotency_key', $key)->first();

        if (! $record) {
            // Anomaly: Insert failed (duplicate), but record is gone?
            // This implies a very tight race where it was deleted.
            throw new ConcurrentRequestException('Race condition detected: Lock lost.');
        }

        // Integrity Check: Preventing Collision Attacks
        if ($record->command_hash !== $hash) {
            throw new IdempotencyKeyReuseException('Idempotency key reused with different payload.');
        }

        // State Machine
        return match ($record->status) {
            'COMPLETED' => $this->completedPayload($record, $key),
            'PROCESSING' => $this->checkZombieAndRecover($record, $key, $operation),
            'FAILED' => throw new \RuntimeException('Previous attempt failed. Please retry with a new key.'),
            default => throw new \RuntimeException("Unknown state: {$record->status}"),
        };
    }

    private function completedPayload(object $record, string $key): mixed
    {
        $payload = json_decode($record->response_payload ?? '[]', true);

        if (! is_array($payload) || ! isset($payload['v']) || $payload['v'] !== self::PAYLOAD_VERSION) {
            DB::table('processed_commands')->where('idempotency_key', $key)->delete();

            throw new ConcurrentRequestException('Idempotency schema version mismatch. State flushed. Please retry.');
        }

        return $payload['data'] ?? null;
    }

    /**
     * Analyzes lock age and performs Hostile Takeover if necessary.
     */
    private function checkZombieAndRecover(object $record, string $key, Closure $operation): mixed
    {
        $updatedAt = \Carbon\Carbon::parse($record->updated_at);
        $diff = abs(now()->diffInSeconds($updatedAt));

        // Case A: Process Alive (Throttle)
        if ($diff < self::LOCK_TTL_SECONDS) {
            throw new ConcurrentRequestException('Command is currently being processed.');
        }

        // Case B: Zombie Detected (Hostile Takeover)
        // We assume the previous process crashed. We attempt to steal the lock.
        // We use Optimistic Concurrency Control (where updated_at matches).

        $affected = DB::table('processed_commands')
            ->where('idempotency_key', $key)
            ->where('status', 'PROCESSING') // Safety check
            ->where('updated_at', $record->updated_at) // Optimistic Lock
            ->update([
                'updated_at' => now(),
                // We keep status as PROCESSING, effectively renewing the lease
            ]);

        if ($affected === 0) {
            // We lost the race. Another thread recovered it or it finished.
            // Recursion to re-evaluate the new state.
            return $this->execute($key, $record->tenant_id, $record->command_hash, $operation);
        }

        // We won the race.
        Log::warning("Recovering zombie lock for key: {$key}");

        // Proceed to execute logic as the new owner
        return $this->runOperation($key, $operation);
    }

    private function isDuplicateEntryException(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $message = $e->getMessage();

        return $code === '23000'
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'unique constraint');
    }
}
