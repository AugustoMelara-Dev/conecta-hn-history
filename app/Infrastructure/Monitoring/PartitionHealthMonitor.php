<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PartitionHealthMonitor - Level 46 (Hot Shard Detection)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HOT SHARD DETECTION: Identifies partitions with excessive throughput    ║
 * ║  AGGREGATE BOTTLENECK: Detects aggregates consuming too much capacity    ║
 * ║  PROMETHEUS READY: Exposes metrics for scraping                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PartitionHealthMonitor
{
    private const PENDING_THRESHOLD = 100; // Alert if partition has > 100 pending
    private const RECOVERY_THRESHOLD = 3;  // Alert if message recovered > 3 times

    /**
     * Get health metrics for all partitions.
     * 
     * @return array<int, array{pending: int, processing: int, failed: int}>
     */
    public function getPartitionHealth(): array
    {
        $stats = DB::table('outbox_messages')
            ->select([
                'partition_key',
                DB::raw("SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) as processing"),
                DB::raw("SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed"),
            ])
            ->groupBy('partition_key')
            ->get();

        $health = [];
        foreach ($stats as $row) {
            $health[$row->partition_key] = [
                'pending' => (int) $row->pending,
                'processing' => (int) $row->processing,
                'failed' => (int) $row->failed,
            ];
        }

        return $health;
    }

    /**
     * Detect "hot" partitions that are consuming disproportionate throughput.
     * 
     * @return array<int, int> Map of partition_key => pending count
     */
    public function detectHotShards(): array
    {
        $health = $this->getPartitionHealth();
        $hotShards = [];

        foreach ($health as $partition => $stats) {
            if ($stats['pending'] > self::PENDING_THRESHOLD) {
                $hotShards[$partition] = $stats['pending'];
                
                Log::warning('HOT SHARD DETECTED', [
                    'partition_key' => $partition,
                    'pending_count' => $stats['pending'],
                    'threshold' => self::PENDING_THRESHOLD,
                    'action_required' => 'Consider rebalancing or adding workers for this shard',
                ]);
            }
        }

        return $hotShards;
    }

    /**
     * Detect aggregates that are causing repeated failures (toxic aggregates).
     * 
     * @return array<string, array{recovery_count: int, failed_count: int}>
     */
    public function detectToxicAggregates(): array
    {
        $toxic = DB::table('outbox_messages')
            ->select([
                'aggregate_id',
                DB::raw('MAX(recovery_count) as max_recovery'),
                DB::raw("SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_count"),
            ])
            ->whereNotNull('aggregate_id')
            ->groupBy('aggregate_id')
            ->having('max_recovery', '>', self::RECOVERY_THRESHOLD)
            ->orHaving('failed_count', '>', 0)
            ->get();

        $result = [];
        foreach ($toxic as $row) {
            $result[$row->aggregate_id] = [
                'recovery_count' => (int) $row->max_recovery,
                'failed_count' => (int) $row->failed_count,
            ];

            Log::alert('TOXIC AGGREGATE DETECTED', [
                'aggregate_id' => $row->aggregate_id,
                'recovery_count' => $row->max_recovery,
                'failed_count' => $row->failed_count,
                'action_required' => 'Manual investigation required',
            ]);
        }

        return $result;
    }

    /**
     * Get overall system health summary.
     */
    public function getSystemHealth(): array
    {
        return [
            'partition_health' => $this->getPartitionHealth(),
            'hot_shards' => $this->detectHotShards(),
            'toxic_aggregates' => $this->detectToxicAggregates(),
            'sequence_table_size' => DB::table('outbox_sequences')->count(),
            'total_pending' => OutboxMessage::where('status', OutboxMessage::STATUS_PENDING)->count(),
            'total_processing' => OutboxMessage::where('status', OutboxMessage::STATUS_PROCESSING)->count(),
            'total_failed' => OutboxMessage::where('status', OutboxMessage::STATUS_FAILED)->count(),
        ];
    }
}
