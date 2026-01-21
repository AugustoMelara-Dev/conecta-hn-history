<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Application\Common\Contracts\MetricsCollectorInterface;
use Illuminate\Support\Facades\Log;

/**
 * DatadogMetricsAdapter - Observability Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE ISOLATION: Decouples app from 'dogstatsd' binaries.     ║
 * ║  PRODUCTION READY: Uses UDP transport via library (simulated here).    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DatadogMetricsAdapter implements MetricsCollectorInterface
{
    /**
     * Increment a counter metric.
     *
     * @param  string  $metric  Name of the metric (e.g., 'circuit_breaker.failure')
     * @param  array  $tags  Key-value pairs for dimensional tagging
     */
    public function increment(string $metric, array $tags = []): void
    {
        // SIMULATION: In production, this would use DataDog/DogStatsd::increment
        // For this environment, we log structure metrics to verify intent.

        Log::debug("METRIC [INC]: {$metric}", $tags);
    }

    /**
     * Record a gauge value (snapshot).
     *
     * @param  string  $metric  Name of the metric
     * @param  float  $value  The current value
     * @param  array  $tags  Dimensions
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        Log::debug("METRIC [GAUGE]: {$metric} = {$value}", $tags);
    }
}
