<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ResilientMetricsManager - Level 46 (Zero Log-DoS Architecture)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CIRCUIT BREAKER: Switches to NullDriver on transport failure            ║
 * ║  THROTTLED LOGGING: Max 1 warning per 5 minutes during outage            ║
 * ║  FAIL-SILENT: Metrics never interrupt business operations                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ResilientMetricsManager
{
    private const CIRCUIT_BREAKER_KEY = 'metrics:circuit_breaker:open';

    private const CIRCUIT_BREAKER_TTL = 60; // seconds

    private const THROTTLE_KEY = 'metrics:log_throttle:last_warning';

    private const THROTTLE_INTERVAL = 300; // 5 minutes

    private bool $circuitOpen = false;

    /**
     * Record a metric with circuit breaker protection.
     *
     * @param  string  $name  Metric name
     * @param  mixed  $value  Metric value
     * @param  array<string, mixed>  $tags  Additional tags
     */
    public function record(string $name, mixed $value, array $tags = []): void
    {
        // Check if circuit is open
        if ($this->isCircuitOpen()) {
            return; // Silently skip - NullDriver mode
        }

        try {
            $this->doRecord($name, $value, $tags);
        } catch (Throwable $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Record processing latency with resilience.
     */
    public function recordLatency(string $operation, float $milliseconds, array $tags = []): void
    {
        $this->record("outbox.{$operation}.latency_ms", $milliseconds, $tags);
    }

    /**
     * Increment a counter with resilience.
     */
    public function increment(string $name, int $count = 1, array $tags = []): void
    {
        $this->record($name, $count, array_merge($tags, ['type' => 'counter']));
    }

    /**
     * Check if circuit breaker is open.
     */
    private function isCircuitOpen(): bool
    {
        if ($this->circuitOpen) {
            return true;
        }

        try {
            return Cache::get(self::CIRCUIT_BREAKER_KEY, false);
        } catch (Throwable) {
            // Cache itself is down - assume circuit is open
            $this->circuitOpen = true;

            return true;
        }
    }

    /**
     * Open the circuit breaker.
     */
    private function openCircuit(): void
    {
        $this->circuitOpen = true;

        try {
            Cache::put(self::CIRCUIT_BREAKER_KEY, true, self::CIRCUIT_BREAKER_TTL);
        } catch (Throwable) {
            // Cache is down, in-memory flag is enough
        }
    }

    /**
     * Handle metric recording failure.
     */
    private function handleFailure(Throwable $e): void
    {
        // Open circuit breaker
        $this->openCircuit();

        // Throttled logging - max 1 warning per 5 minutes
        if ($this->shouldLogWarning()) {
            Log::warning('METRICS CIRCUIT BREAKER OPEN: Transport failure', [
                'error' => $e->getMessage(),
                'circuit_ttl_seconds' => self::CIRCUIT_BREAKER_TTL,
                'next_log_in_seconds' => self::THROTTLE_INTERVAL,
            ]);

            $this->markWarningLogged();
        }
    }

    /**
     * Check if we should log a warning (throttled).
     */
    private function shouldLogWarning(): bool
    {
        try {
            $lastWarning = Cache::get(self::THROTTLE_KEY);

            if ($lastWarning === null) {
                return true;
            }

            return now()->timestamp - (int) $lastWarning >= self::THROTTLE_INTERVAL;
        } catch (Throwable) {
            // Cache is down, log conservatively
            return false;
        }
    }

    /**
     * Mark that we logged a warning.
     */
    private function markWarningLogged(): void
    {
        try {
            Cache::put(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_INTERVAL * 2);
        } catch (Throwable) {
            // Ignore - in-memory tracking will suffice
        }
    }

    /**
     * Actual metric recording implementation.
     * Override this for different backends (Redis, CloudWatch, Prometheus, etc.)
     */
    protected function doRecord(string $name, mixed $value, array $tags): void
    {
        // Default implementation: structured log for log aggregation
        // Can be replaced with actual metrics transport
        Log::info('metric', [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Reset circuit breaker (for testing or manual recovery).
     */
    public function resetCircuit(): void
    {
        $this->circuitOpen = false;

        try {
            Cache::forget(self::CIRCUIT_BREAKER_KEY);
            Cache::forget(self::THROTTLE_KEY);
        } catch (Throwable) {
            // Ignore
        }
    }
}
