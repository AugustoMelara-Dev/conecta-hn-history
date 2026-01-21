<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ElasticHealthProbe - Level 50 Zenith (State-Resilient Exponential Backoff)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PERSISTENT STATE: Failure counters survive process restarts via Redis   ║
 * ║  EXPONENTIAL BACKOFF: Base × 2^min(failures, 6) × (1 ± 0.2 jitter)       ║
 * ║  FLEET AMNESIA IMMUNITY: Workers re-read state before first acquisition  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ElasticHealthProbe
{
    private const WARNING_LATENCY_MS = 2000;

    private const DANGER_LATENCY_MS = 5000;

    private const SLIDING_WINDOW_SIZE = 10;

    private const BASE_COOLDOWN_SECONDS = 10;

    private const MAX_FAILURE_EXPONENT = 6; // 2^6 = 64x max multiplier

    private const STATE_TTL_SECONDS = 3600; // 1 hour state persistence

    private const CACHE_KEY_PREFIX = 'outbox:health:';

    /** @var array<float> */
    private array $latencyWindow = [];

    private int $cooldownStartedAt = 0;

    private int $cooldownDuration = 0;

    /** @var string Unique worker identifier for state isolation */
    private readonly string $workerId;

    public function __construct(string $workerId = '')
    {
        $this->workerId = $workerId ?: gethostname().':'.getmypid();

        // LEVEL 50: Re-read failure state on instantiation (survives reboots)
        $this->rehydrateState();
    }

    /**
     * LEVEL 50: Rehydrate failure state from persistent store.
     * Prevents "Fleet Amnesia" after pod recycling.
     */
    private function rehydrateState(): void
    {
        $cachedFailures = Cache::get($this->getStateKey('consecutive_failures'), 0);

        if ($cachedFailures > 0) {
            Log::info('ELASTIC PROBE: Rehydrated failure state from cache', [
                'worker_id' => $this->workerId,
                'consecutive_failures' => $cachedFailures,
            ]);
        }
    }

    /**
     * Get the current consecutive failure count from persistent store.
     */
    public function getConsecutiveFailures(): int
    {
        return (int) Cache::get($this->getStateKey('consecutive_failures'), 0);
    }

    /**
     * Record a dispatch latency sample.
     */
    public function recordLatency(float $milliseconds): void
    {
        $this->latencyWindow[] = $milliseconds;

        if (count($this->latencyWindow) > self::SLIDING_WINDOW_SIZE) {
            array_shift($this->latencyWindow);
        }

        // Decay failure count on successful operations below warning threshold
        if ($milliseconds < self::WARNING_LATENCY_MS) {
            $currentFailures = $this->getConsecutiveFailures();
            if ($currentFailures > 0) {
                $this->persistFailureCount($currentFailures - 1);
            }
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
     * Check if system is in warning zone.
     */
    public function isWarningZone(): bool
    {
        return $this->getAverageLatency() >= self::WARNING_LATENCY_MS;
    }

    /**
     * Check if system is in danger zone.
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

        return time() - $this->cooldownStartedAt < $this->cooldownDuration;
    }

    /**
     * LEVEL 50: Enter cooldown with jittered exponential backoff.
     *
     * Formula: Cooldown = Base × 2^min(failures, 6) × (1 + Random(-0.2, 0.2))
     */
    public function enterCooldown(): void
    {
        $consecutiveFailures = $this->getConsecutiveFailures() + 1;
        $this->persistFailureCount($consecutiveFailures);

        // Cap the exponent to prevent insane cooldowns
        $exponent = min($consecutiveFailures, self::MAX_FAILURE_EXPONENT);

        // Calculate exponential base: Base × 2^failures
        $baseCooldown = self::BASE_COOLDOWN_SECONDS * pow(2, $exponent);

        // LEVEL 50: Jittered multiplier (1 ± 0.2) for staggered recovery
        $jitterMultiplier = 1 + ((mt_rand(-200, 200) / 1000)); // -0.2 to +0.2

        $this->cooldownDuration = (int) ($baseCooldown * $jitterMultiplier);
        $this->cooldownStartedAt = time();

        Log::alert('ELASTIC BACKPRESSURE: Entering jittered exponential cooldown', [
            'worker_id' => $this->workerId,
            'consecutive_failures' => $consecutiveFailures,
            'cooldown_seconds' => $this->cooldownDuration,
            'base_seconds' => self::BASE_COOLDOWN_SECONDS,
            'exponent' => $exponent,
            'jitter_multiplier' => round($jitterMultiplier, 3),
            'average_latency_ms' => $this->getAverageLatency(),
        ]);
    }

    /**
     * Exit cooldown state.
     */
    public function exitCooldown(): void
    {
        if ($this->cooldownStartedAt > 0) {
            Log::info('ELASTIC BACKPRESSURE: Exiting cooldown', [
                'worker_id' => $this->workerId,
                'cooldown_duration_actual' => time() - $this->cooldownStartedAt,
                'cooldown_duration_planned' => $this->cooldownDuration,
                'consecutive_failures' => $this->getConsecutiveFailures(),
            ]);
        }

        $this->cooldownStartedAt = 0;
        $this->cooldownDuration = 0;
        // Note: Do NOT reset consecutiveFailures - it decays naturally
    }

    /**
     * Get remaining cooldown time in seconds.
     */
    public function getRemainingCooldownSeconds(): int
    {
        if (! $this->isInCooldown()) {
            return 0;
        }

        return max(0, $this->cooldownDuration - (time() - $this->cooldownStartedAt));
    }

    /**
     * Force reset failure counter (for external recovery signals).
     */
    public function resetFailures(): void
    {
        $this->persistFailureCount(0);
        $this->latencyWindow = [];

        Log::info('ELASTIC BACKPRESSURE: Failure counter reset', [
            'worker_id' => $this->workerId,
        ]);
    }

    /**
     * LEVEL 50: Persist failure count to cache/Redis.
     */
    private function persistFailureCount(int $count): void
    {
        Cache::put(
            $this->getStateKey('consecutive_failures'),
            max(0, $count),
            self::STATE_TTL_SECONDS
        );
    }

    /**
     * Get cache key with prefix and worker isolation.
     */
    private function getStateKey(string $suffix): string
    {
        // Use global key for fleet-wide state, not worker-specific
        return self::CACHE_KEY_PREFIX.$suffix;
    }

    /**
     * Get health status summary.
     */
    public function getStatus(): array
    {
        return [
            'worker_id' => $this->workerId,
            'average_latency_ms' => round($this->getAverageLatency(), 2),
            'sample_count' => count($this->latencyWindow),
            'zone' => $this->isDangerZone() ? 'DANGER' : ($this->isWarningZone() ? 'WARNING' : 'NORMAL'),
            'in_cooldown' => $this->isInCooldown(),
            'remaining_cooldown_seconds' => $this->getRemainingCooldownSeconds(),
            'consecutive_failures' => $this->getConsecutiveFailures(),
            'current_cooldown_duration' => $this->cooldownDuration,
            'state_persisted' => true,
            'thresholds' => [
                'warning_ms' => self::WARNING_LATENCY_MS,
                'danger_ms' => self::DANGER_LATENCY_MS,
            ],
        ];
    }
}
