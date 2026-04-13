<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\MetricsCollectorInterface;

final readonly class DomainTelemetry
{
    public function __construct(
        private LoggerInterface $logger,
        private MetricsCollectorInterface $metrics,
        private ObservabilityContextFactory $contextFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $domain, string $event, array $context = [], string $level = 'info'): void
    {
        $payload = $this->contextFactory->make(array_merge($context, [
            'domain' => $domain,
            'event_name' => $event,
        ]));

        match ($level) {
            'debug' => $this->logger->debug($event, $payload),
            'warning' => $this->logger->warning($event, $payload),
            'error' => $this->logger->error($event, $payload),
            'critical' => $this->logger->critical($event, $payload),
            default => $this->logger->info($event, $payload),
        };

        $this->metrics->increment('conecta.domain.event.total', $this->metricTags($domain, $event, $context));
    }

    /**
     * @param  array<string, scalar|int|float|string|bool|null>  $tags
     * @param  array<string, mixed>  $context
     */
    public function gauge(string $domain, string $metric, float $value, array $tags = [], array $context = []): void
    {
        $resolvedTags = array_merge(['domain' => $domain], $this->sanitizeTags($tags));

        $this->metrics->gauge($metric, $value, $resolvedTags);
        $this->logger->debug('domain_metric_recorded', $this->contextFactory->make(array_merge($context, [
            'domain' => $domain,
            'metric_name' => $metric,
            'metric_value' => $value,
            'metric_tags' => $resolvedTags,
        ])));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function metricTags(string $domain, string $event, array $context): array
    {
        $tags = ['domain' => $domain, 'event' => $event];

        foreach (['status', 'channel', 'provider', 'outcome'] as $tagKey) {
            $value = $context[$tagKey] ?? null;

            if (is_scalar($value) && $value !== '') {
                $tags[$tagKey] = (string) $value;
            }
        }

        return $tags;
    }

    /**
     * @param  array<string, scalar|int|float|string|bool|null>  $tags
     * @return array<string, string>
     */
    private function sanitizeTags(array $tags): array
    {
        $sanitized = [];

        foreach ($tags as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $sanitized[$key] = (string) $value;
        }

        return $sanitized;
    }
}
