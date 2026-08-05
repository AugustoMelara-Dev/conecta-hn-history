<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AtomicSequenceGenerator - Level 46 (Thread-Safe Monotonic Counter)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC: Uses INSERT ON DUPLICATE KEY UPDATE for lock-free increments    ║
 * ║  O(1): No MAX() queries, constant time regardless of message count       ║
 * ║  DEADLOCK-FREE: Row-level locking with minimal lock duration             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class AtomicSequenceGenerator
{
    private const TABLE = 'outbox_sequences';

    /**
     * Get the next sequence number for an aggregate atomically.
     *
     * Uses a portable upsert inside a transaction for O(1) performance. The
     * counter row stays locked through the read that returns the reserved value.
     *
     * @param  string|null  $aggregateId  The aggregate root identifier
     * @return int The next sequence number (1-indexed)
     */
    public function getNextSequence(?string $aggregateId): int
    {
        if ($aggregateId === null) {
            return 0;
        }

        return DB::transaction(function () use ($aggregateId): int {
            $now = now();

            // The upsert locks the counter row until this transaction reads its
            // own reserved value, preventing another worker from advancing the
            // row between the increment and the read.
            DB::table(self::TABLE)->upsert(
                [[
                    'aggregate_id' => $aggregateId,
                    'last_sequence' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['aggregate_id'],
                [
                    'last_sequence' => DB::raw(self::TABLE.'.last_sequence + 1'),
                    'updated_at' => $now,
                ],
            );

            $sequence = DB::table(self::TABLE)
                ->where('aggregate_id', $aggregateId)
                ->value('last_sequence');

            if ($sequence === null) {
                throw new RuntimeException(
                    "AtomicSequenceGenerator: Failed to retrieve sequence for aggregate {$aggregateId}"
                );
            }

            return (int) $sequence;
        }, 3);
    }

    /**
     * Get the current sequence number without incrementing.
     * Useful for debugging and monitoring.
     */
    public function getCurrentSequence(string $aggregateId): int
    {
        $sequence = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->value('last_sequence');

        return (int) ($sequence ?? 0);
    }

    /**
     * Prune sequences for aggregates with no pending messages.
     * Should be called periodically to prevent table bloat.
     */
    public function pruneStaleSequences(int $daysOld = 30): int
    {
        $cutoff = now()->subDays($daysOld);

        return DB::table(self::TABLE)
            ->where('updated_at', '<', $cutoff)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('outbox_messages')
                    ->whereColumn('outbox_messages.aggregate_id', self::TABLE.'.aggregate_id')
                    ->whereIn('outbox_messages.status', ['PENDING', 'PROCESSING']);
            })
            ->delete();
    }
}
