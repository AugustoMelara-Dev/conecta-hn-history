<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

interface MetricsCollectorInterface
{
    /**
     * Increment a counter metric.
     *
     * @param  string  $metric  Name of the metric
     * @param  array  $tags  Key-value pairs for dimensional tagging
     */
    public function increment(string $metric, array $tags = []): void;

    /**
     * Record a gauge value (snapshot).
     *
     * @param  string  $metric  Name of the metric
     * @param  float  $value  The current value
     * @param  array  $tags  Dimensions
     */
    public function gauge(string $metric, float $value, array $tags = []): void;
}
