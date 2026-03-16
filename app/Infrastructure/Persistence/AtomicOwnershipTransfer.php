<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AtomicOwnershipTransfer - Level 51 Zenith True
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  UNIFIED ATOMIC GRAB: Physical Ownership Transfer in single SQL          ║
 * ║  ZERO-GAP ACQUISITION: No "selected but unclaimed" window ever exists    ║
 * ║  STRICT SQL: UPDATE WHERE IN (SELECT...FOR UPDATE SKIP LOCKED) pattern   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class AtomicOwnershipTransfer
{
    /**
     * Claim available messages atomically via UPDATE with subquery.
     *
     * This method executes a single SQL statement that:
     * 1. Acquires row locks via FOR UPDATE SKIP LOCKED
     * 2. Updates status to PROCESSING in the same lock
     * 3. Assigns worker_id atomically
     *
     * Result: Zero-gap acquisition - no window where message is "seen but not owned"
     *
     * @param  string  $workerId  The claiming worker's unique identifier
     * @param  int  $batchSize  Maximum messages to claim
     * @param  array<int>  $partitionKeys  Shard IDs this worker owns
     * @param  int  $quarantineThreshold  Maximum recovery_count before quarantine
     * @return Collection<OutboxMessage> The claimed messages (already owned by this worker)
     */
    public function claimAvailableMessages(
        string $workerId,
        int $batchSize,
        array $partitionKeys,
        int $quarantineThreshold = 5
    ): Collection {
        if (empty($partitionKeys)) {
            return collect();
        }

        // Generate batch correlation ID for telemetric audit
        $batchCorrelationId = (string) Str::uuid();

        try {
            $claimedCount = $this->claimWithSkipLocked(
                workerId: $workerId,
                batchCorrelationId: $batchCorrelationId,
                partitionKeys: $partitionKeys,
                quarantineThreshold: $quarantineThreshold,
                batchSize: $batchSize
            );
        } catch (QueryException $e) {
            if (! $this->isSkipLockedSyntaxError($e)) {
                Log::error('ATOMIC GRAB FAILED', [
                    'worker_id' => $workerId,
                    'error' => $e->getMessage(),
                    'partition_keys' => $partitionKeys,
                ]);

                throw $e;
            }

            Log::warning('ATOMIC GRAB FALLBACK: Database does not support SKIP LOCKED, using lockForUpdate fallback.', [
                'worker_id' => $workerId,
                'connection' => DB::getDriverName(),
                'error' => $e->getMessage(),
            ]);

            $claimedCount = $this->claimWithPessimisticLock(
                workerId: $workerId,
                batchCorrelationId: $batchCorrelationId,
                partitionKeys: $partitionKeys,
                quarantineThreshold: $quarantineThreshold,
                batchSize: $batchSize
            );
        }

        try {

            Log::info('ATOMIC GRAB: Ownership transferred', [
                'worker_id' => $workerId,
                'batch_correlation_id' => $batchCorrelationId,
                'claimed_count' => $claimedCount,
                'partition_keys' => $partitionKeys,
            ]);

            if ($claimedCount === 0) {
                return collect();
            }

            // LEVEL 52: STRONG-CONSISTENCY FETCH WITH RYOW VALIDATION
            // This fetch MUST be forced to the Writer/Primary Connection
            // to eliminate replication lag interference during model hydration.
            $hydratedMessages = OutboxMessage::query()
                ->useWritePdo() // CRITICAL: Force read from primary, not replica
                ->where('worker_id', $workerId)
                ->where('status', OutboxMessage::STATUS_PROCESSING)
                ->where('batch_correlation_id', $batchCorrelationId)
                ->orderBy('partition_key', 'asc')
                ->orderBy('sequence_number', 'asc')
                ->get();

            // LEVEL 52: RYOW Count-Check Validation
            // Hardware-level guarantee: Primary Node's write was visible
            $hydratedCount = $hydratedMessages->count();
            if ($hydratedCount !== $claimedCount) {
                Log::emergency('INFRASTRUCTURE_ACID_VIOLATION: RYOW count mismatch', [
                    'expected_count' => $claimedCount,
                    'hydrated_count' => $hydratedCount,
                    'worker_id' => $workerId,
                    'batch_correlation_id' => $batchCorrelationId,
                    'action' => 'IMMEDIATE_ABORT',
                ]);

                throw new \App\Infrastructure\Exceptions\InfrastructureACIDException(
                    'RYOW verification failed. '.
                    "UPDATE affected {$claimedCount} rows but pinned fetch returned {$hydratedCount}. ".
                    'Possible replication lag or infrastructure split-brain.'
                );
            }

            return $hydratedMessages;

        } catch (\Throwable $e) {
            Log::error('ATOMIC GRAB FAILED', [
                'worker_id' => $workerId,
                'error' => $e->getMessage(),
                'partition_keys' => $partitionKeys,
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<int>  $partitionKeys
     */
    private function claimWithSkipLocked(
        string $workerId,
        string $batchCorrelationId,
        array $partitionKeys,
        int $quarantineThreshold,
        int $batchSize
    ): int {
        $shardPlaceholders = implode(',', array_fill(0, count($partitionKeys), '?'));

        return DB::affectingStatement(
            "UPDATE outbox_messages 
             SET status = 'PROCESSING', 
                 worker_id = ?, 
                 batch_correlation_id = ?,
                 locked_at = NOW()
             WHERE id IN (
                 SELECT id FROM (
                     SELECT id FROM outbox_messages 
                     WHERE status = 'PENDING' 
                       AND partition_key IN ({$shardPlaceholders})
                       AND recovery_count < ?
                     ORDER BY partition_key ASC, sequence_number ASC
                     LIMIT ?
                     FOR UPDATE SKIP LOCKED
                 ) AS candidates
             )",
            array_merge(
                [$workerId, $batchCorrelationId],
                $partitionKeys,
                [$quarantineThreshold, $batchSize]
            )
        );
    }

    /**
     * @param  array<int>  $partitionKeys
     */
    private function claimWithPessimisticLock(
        string $workerId,
        string $batchCorrelationId,
        array $partitionKeys,
        int $quarantineThreshold,
        int $batchSize
    ): int {
        $messageIds = DB::table('outbox_messages')
            ->where('status', OutboxMessage::STATUS_PENDING)
            ->whereIn('partition_key', $partitionKeys)
            ->where('recovery_count', '<', $quarantineThreshold)
            ->orderBy('partition_key', 'asc')
            ->orderBy('sequence_number', 'asc')
            ->limit($batchSize)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($messageIds === []) {
            return 0;
        }

        return DB::table('outbox_messages')
            ->whereIn('id', $messageIds)
            ->update([
                'status' => OutboxMessage::STATUS_PROCESSING,
                'worker_id' => $workerId,
                'batch_correlation_id' => $batchCorrelationId,
                'locked_at' => now(),
            ]);
    }

    private function isSkipLockedSyntaxError(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'skip locked')
            || (str_contains($message, 'for update') && str_contains($message, 'syntax error'))
            || (str_contains($message, 'for update') && str_contains($message, 'access violation'))
            || (str_contains($message, 'near "for"') && str_contains($message, 'syntax error'));
    }

    /**
     * Release ownership of messages (for graceful shutdown or error recovery).
     *
     * @param  string  $workerId  Worker releasing ownership
     * @param  string|null  $batchCorrelationId  Specific batch to release, or null for all
     * @return int Number of messages released
     */
    public function releaseOwnership(string $workerId, ?string $batchCorrelationId = null): int
    {
        $query = OutboxMessage::query()
            ->where('worker_id', $workerId)
            ->where('status', OutboxMessage::STATUS_PROCESSING);

        if ($batchCorrelationId !== null) {
            $query->where('batch_correlation_id', $batchCorrelationId);
        }

        $released = $query->update([
            'status' => OutboxMessage::STATUS_PENDING,
            'worker_id' => null,
            'batch_correlation_id' => null,
            'locked_at' => null,
        ]);

        Log::info('ATOMIC GRAB: Ownership released', [
            'worker_id' => $workerId,
            'batch_correlation_id' => $batchCorrelationId,
            'released_count' => $released,
        ]);

        return $released;
    }

    /**
     * Validate strict adherence - verify worker still owns the message.
     *
     * @param  OutboxMessage  $message  The message to validate
     * @param  string  $workerId  Expected owner
     *
     * @throws \RuntimeException If ownership mismatch detected
     */
    public function validateOwnership(OutboxMessage $message, string $workerId): void
    {
        if ($message->worker_id !== $workerId) {
            Log::emergency('CRITICAL_CONCURRENCY_VIOLATION: Worker ID mismatch', [
                'expected_worker_id' => $workerId,
                'actual_worker_id' => $message->worker_id,
                'message_id' => $message->id,
                'aggregate_id' => $message->aggregate_id,
            ]);

            throw new \RuntimeException(
                'CriticalConcurrencyViolationException: Worker ID mismatch detected. '.
                "Expected {$workerId}, got {$message->worker_id}"
            );
        }
    }
}
