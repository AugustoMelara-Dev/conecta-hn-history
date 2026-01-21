<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BufferedSequenceGenerator - Level 47 (Range Buffering)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  50x LESS CONTENTION: Reserves blocks of sequences in single operation   ║
 * ║  IN-MEMORY DISPENSING: No DB hit for most sequence assignments           ║
 * ║  GAP-TOLERANT: Accepts gaps in sequence numbers on crashes               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BufferedSequenceGenerator
{
    private const TABLE = 'outbox_sequences';

    private const BUFFER_SIZE = 50; // Reserve 50 sequences at a time

    /**
     * In-memory buffer of reserved sequences per aggregate.
     *
     * @var array<string, array{next: int, max: int}>
     */
    private array $buffers = [];

    /**
     * Get the next sequence number for an aggregate.
     *
     * Uses in-memory buffer when available, otherwise reserves a new block.
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

        // Check if we have buffered sequences available
        if ($this->hasBufferedSequence($aggregateId)) {
            return $this->dispenseFromBuffer($aggregateId);
        }

        // Reserve a new block of sequences
        $this->reserveBlock($aggregateId);

        return $this->dispenseFromBuffer($aggregateId);
    }

    /**
     * Check if there are buffered sequences available.
     */
    private function hasBufferedSequence(string $aggregateId): bool
    {
        if (! isset($this->buffers[$aggregateId])) {
            return false;
        }

        return $this->buffers[$aggregateId]['next'] <= $this->buffers[$aggregateId]['max'];
    }

    /**
     * Dispense a sequence from the in-memory buffer.
     */
    private function dispenseFromBuffer(string $aggregateId): int
    {
        $sequence = $this->buffers[$aggregateId]['next'];
        $this->buffers[$aggregateId]['next']++;

        return $sequence;
    }

    /**
     * Reserve a block of sequences atomically.
     *
     * Uses INSERT ON DUPLICATE KEY UPDATE to atomically increment by BUFFER_SIZE.
     */
    private function reserveBlock(string $aggregateId): void
    {
        $now = now()->toDateTimeString();

        // Atomic block reservation
        // This increments by BUFFER_SIZE in a single operation
        DB::statement(
            'INSERT INTO '.self::TABLE.' (aggregate_id, last_sequence, created_at, updated_at) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
                last_sequence = last_sequence + ?, 
                updated_at = ?',
            [$aggregateId, self::BUFFER_SIZE, $now, $now, self::BUFFER_SIZE, $now]
        );

        // Fetch the new max value
        $maxSequence = DB::table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->value('last_sequence');

        if ($maxSequence === null) {
            throw new RuntimeException(
                "BufferedSequenceGenerator: Failed to reserve block for aggregate {$aggregateId}"
            );
        }

        // Set up the buffer
        // The range is (max - BUFFER_SIZE + 1) to max
        $this->buffers[$aggregateId] = [
            'next' => (int) $maxSequence - self::BUFFER_SIZE + 1,
            'max' => (int) $maxSequence,
        ];
    }

    /**
     * Get buffer status for debugging.
     *
     * @return array<string, array{remaining: int, next: int, max: int}>
     */
    public function getBufferStatus(): array
    {
        $status = [];
        foreach ($this->buffers as $aggregateId => $buffer) {
            $status[$aggregateId] = [
                'remaining' => $buffer['max'] - $buffer['next'] + 1,
                'next' => $buffer['next'],
                'max' => $buffer['max'],
            ];
        }

        return $status;
    }

    /**
     * Clear all buffers (for testing or graceful shutdown).
     */
    public function clearBuffers(): void
    {
        $this->buffers = [];
    }

    /**
     * Get the buffer size.
     */
    public static function getBufferSize(): int
    {
        return self::BUFFER_SIZE;
    }
}
