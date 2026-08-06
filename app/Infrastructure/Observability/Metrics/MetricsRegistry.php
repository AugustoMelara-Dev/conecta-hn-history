<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Metrics;

/**
 * MetricsRegistry - Prometheus-style metrics counters.
 *
 * Implementación en memoria (para dev/test). En producción conectar a
 * open-telemetry o prometheus_client.
 */
class MetricsRegistry
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, float> */
    private array $gauges = [];

    public function increment(string $name, int $value = 1, array $labels = []): void
    {
        $key = $this->buildKey($name, $labels);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildKey($name, $labels);
        $this->gauges[$key] = $value;
    }

    /**
     * @return array<string, int>
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    /**
     * @return array<string, float>
     */
    public function getGauges(): array
    {
        return $this->gauges;
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
    }

    private function buildKey(string $name, array $labels): string
    {
        if (empty($labels)) {
            return $name;
        }
        ksort($labels);
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = "{$k}={$v}";
        }
        return $name . '{' . implode(',', $parts) . '}';
    }
}
