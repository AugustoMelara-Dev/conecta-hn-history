<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Log;

/**
 * SystemHealthProbe - Level 48 (Backpressure Feedback Loop)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REACTIVE: Monitors downstream latency to adjust ingestion rate          ║
 * ║  HUMBLE: Workers slow down when system is struggling                     ║
 * ║  PROTECTIVE: Prevents Outbox fleet from DDoS-ing struggling services     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SystemHealthProbe
{
    private const WARNING_LATENCY_MS = 2000;  // Stop acquiring new shards

    private const DANGER_LATENCY_MS = 5000;   // Release shards and cool down

    private const SLIDING_WINDOW_SIZE = 10;   // Number of samples to average

    private const COOLDOWN_SECONDS = 60;      // How long to sleep in danger zone

    /**
     * Sliding window of dispatch latencies in milliseconds.
     *
     * @var array<float>
     */
    private array $latencyWindow = [];

    /**
     * Timestamp when cooldown started (0 = not in cooldown).
     */
    private int $cooldownStartedAt = 0;

    /**
     * Record a dispatch latency sample.
     */
    public function recordLatency(float $milliseconds): void
    {
        $this->latencyWindow[] = $milliseconds;

        // Keep only the last N samples
        if (count($this->latencyWindow) > self::SLIDING_WINDOW_SIZE) {
            array_shift($this->latencyWindow);
        }
    }

    /**
     * Get the current average dispatch latency.
     */
    public function getAverageLatency(): float
    {
        if (empty($this->latencyWindow)) {
            return 0.0;
        }

        return array_sum($this->latencyWindow) / count($this->latencyWindow);
    }

    /**
     * Check if system is in warning zone (stop acquiring new shards).
     */
    public function isWarningZone(): bool
    {
        return $this->getAverageLatency() >= self::WARNING_LATENCY_MS;
    }

    /**
     * Check if system is in danger zone (release shards and cool down).
     */
    public function isDangerZone(): bool
    {
        return $this->getAverageLatency() >= self::DANGER_LATENCY_MS;
    }

    /**
     * Check if currently in cooldown period.
     */
    public function isInCooldown(): bool
    {
        if ($this->cooldownStartedAt === 0) {
            return false;
        }

        return time() - $this->cooldownStartedAt < self::COOLDOWN_SECONDS;
    }

    /**
     * Enter cooldown state.
     */
    public function enterCooldown(): void
    {
        $this->cooldownStartedAt = time();

        Log::alert('BACKPRESSURE: Entering cooldown due to high downstream latency', [
            'average_latency_ms' => $this->getAverageLatency(),
            'threshold_ms' => self::DANGER_LATENCY_MS,
            'cooldown_seconds' => self::COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Exit cooldown state.
     */
    public function exitCooldown(): void
    {
        if ($this->cooldownStartedAt > 0) {
            Log::info('BACKPRESSURE: Exiting cooldown, resuming normal operation', [
                'cooldown_duration_seconds' => time() - $this->cooldownStartedAt,
            ]);
        }

        $this->cooldownStartedAt = 0;
        $this->latencyWindow = []; // Reset latency window
    }

    /**
     * Get remaining cooldown time in seconds.
     */
    public function getRemainingCooldownSeconds(): int
    {
        if (! $this->isInCooldown()) {
            return 0;
        }

        return max(0, self::COOLDOWN_SECONDS - (time() - $this->cooldownStartedAt));
    }

    /**
     * Get health status summary.
     */
    public function getStatus(): array
    {
        return [
            'average_latency_ms' => round($this->getAverageLatency(), 2),
            'sample_count' => count($this->latencyWindow),
            'zone' => $this->isDangerZone() ? 'DANGER' : ($this->isWarningZone() ? 'WARNING' : 'NORMAL'),
            'in_cooldown' => $this->isInCooldown(),
            'remaining_cooldown_seconds' => $this->getRemainingCooldownSeconds(),
            'thresholds' => [
                'warning_ms' => self::WARNING_LATENCY_MS,
                'danger_ms' => self::DANGER_LATENCY_MS,
            ],
        ];
    }
}
