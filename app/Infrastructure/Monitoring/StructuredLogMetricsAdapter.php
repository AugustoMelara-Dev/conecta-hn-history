<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Application\Shared\Contracts\MetricsCollectorInterface;
use Illuminate\Support\Facades\Log;

final readonly class StructuredLogMetricsAdapter implements MetricsCollectorInterface
{
    /**
     * @param  array<string, mixed>  $tags
     */
    public function increment(string $metric, array $tags = []): void
    {
        Log::debug('metric.increment', [
            'metric' => $metric,
            'tags' => $tags,
        ]);
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        Log::debug('metric.gauge', [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tags,
        ]);
    }
}
