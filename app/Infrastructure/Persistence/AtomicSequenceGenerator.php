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
     * Uses INSERT ... ON DUPLICATE KEY UPDATE pattern for O(1) performance.
     * This is lock-free for new aggregates and minimal-lock for existing ones.
     *
     * PREREQUISITE: Must be called within an active database transaction.
     *
     * @param  string|null  $aggregateId  The aggregate root identifier
     * @return int The next sequence number (1-indexed)
     */
    public function getNextSequence(?string $aggregateId): int
    {
        if ($aggregateId === null) {
            return 0;
        }

        // LEVEL 46: ATOMIC SEQUENCE GENERATION
        // Uses MySQL's INSERT ON DUPLICATE KEY UPDATE for atomic increment.
        // This is O(1) and does not require scanning existing messages.
        $now = now()->toDateTimeString();

        // Attempt atomic upsert
        DB::statement(
            'INSERT INTO '.self::TABLE.' (aggregate_id, last_sequence, created_at, updated_at) 
             VALUES (?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE last_sequence = last_sequence + 1, updated_at = ?',
            [$aggregateId, $now, $now, $now]
        );

        // Fetch the current value (it was just incremented)
        $sequence = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->value('last_sequence');

        if ($sequence === null) {
            throw new RuntimeException(
                "AtomicSequenceGenerator: Failed to retrieve sequence for aggregate {$aggregateId}"
            );
        }

        return (int) $sequence;
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
