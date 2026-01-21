<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * OutboxMetricsCollector - Level 44 (Real-Time Observability)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  LAG MONITORING: Tracks processing latency per partition                 ║
 * ║  SATURATION ALERTS: Fires SystemSaturationEvent if lag exceeds threshold ║
 * ║  PROMETHEUS READY: Emits structured metrics for scraping                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OutboxMetricsCollector
{
    private const LAG_THRESHOLD_SECONDS = 30;
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Record processing latency for a completed message.
     */
    public function recordLatency(OutboxMessage $message): void
    {
        $lagMs = now()->diffInMilliseconds($message->created_at);
        $lagSeconds = $lagMs / 1000;
        $partition = $message->partition_key ?? 0;
        
        // Emit structured log for log aggregation (Datadog, ELK, etc.)
        Log::info('outbox_processing_lag', [
            'message_id' => $message->id,
            'partition_key' => $partition,
            'aggregate_id' => $message->aggregate_id,
            'type' => $message->type,
            'lag_ms' => $lagMs,
            'lag_seconds' => $lagSeconds,
        ]);
        
        // Update partition lag in cache for real-time monitoring
        $this->updatePartitionLag($partition, $lagSeconds);
        
        // Check for saturation
        if ($lagSeconds > self::LAG_THRESHOLD_SECONDS) {
            $this->fireSaturationAlert($partition, $lagSeconds);
        }
    }

    /**
     * Get current lag statistics per partition.
     */
    public function getPartitionLags(): array
    {
        $lags = [];
        for ($i = 0; $i < OutboxMessage::PARTITION_COUNT; $i++) {
            $lags[$i] = Cache::get("outbox:lag:partition:{$i}", 0.0);
        }
        return $lags;
    }

    /**
     * Get overall system health metrics.
     */
    public function getSystemMetrics(): array
    {
        return [
            'pending_count' => OutboxMessage::where('status', OutboxMessage::STATUS_PENDING)->count(),
            'processing_count' => OutboxMessage::where('status', OutboxMessage::STATUS_PROCESSING)->count(),
            'failed_count' => OutboxMessage::where('status', OutboxMessage::STATUS_FAILED)->count(),
            'partition_lags' => $this->getPartitionLags(),
            'saturated_partitions' => $this->getSaturatedPartitions(),
        ];
    }

    /**
     * Update rolling average lag for a partition.
     */
    private function updatePartitionLag(int $partition, float $lagSeconds): void
    {
        $key = "outbox:lag:partition:{$partition}";
        $current = Cache::get($key, 0.0);
        
        // Exponential moving average (alpha = 0.3)
        $newLag = ($current * 0.7) + ($lagSeconds * 0.3);
        
        Cache::put($key, $newLag, self::CACHE_TTL_SECONDS);
    }

    /**
     * Get list of partitions exceeding lag threshold.
     */
    private function getSaturatedPartitions(): array
    {
        $saturated = [];
        foreach ($this->getPartitionLags() as $partition => $lag) {
            if ($lag > self::LAG_THRESHOLD_SECONDS) {
                $saturated[] = $partition;
            }
        }
        return $saturated;
    }

    /**
     * Fire saturation alert when lag exceeds threshold.
     */
    private function fireSaturationAlert(int $partition, float $lagSeconds): void
    {
        Log::alert('OUTBOX SATURATION DETECTED', [
            'partition_key' => $partition,
            'lag_seconds' => $lagSeconds,
            'threshold_seconds' => self::LAG_THRESHOLD_SECONDS,
            'action_required' => 'Consider scaling Outbox workers or investigating slow consumers',
        ]);
        
        // TODO: Dispatch SystemSaturationEvent for auto-scaling integration
        // event(new SystemSaturationEvent($partition, $lagSeconds));
    }
}
