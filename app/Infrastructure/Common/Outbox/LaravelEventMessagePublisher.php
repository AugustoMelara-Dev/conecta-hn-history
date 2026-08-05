<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Outbox;

use App\Infrastructure\Shared\OutboxTenantContextRunner;
use Illuminate\Contracts\Events\Dispatcher;
use JsonException;
use ReflectionMethod;
use RuntimeException;

final readonly class LaravelEventMessagePublisher implements MessagePublisherInterface
{
    public function __construct(
        private Dispatcher $events,
        private OutboxTenantContextRunner $tenantContext,
    ) {}

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    public function publish(string $topic, string $payload): void
    {
        $envelope = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($envelope)) {
            throw new RuntimeException('Outbox payload must decode to a JSON object.');
        }

        $data = $envelope['data'] ?? $envelope;

        if (! is_array($data)) {
            throw new RuntimeException('Outbox event data must be a JSON object.');
        }

        if (! class_exists($topic)) {
            throw new RuntimeException("Outbox event type [{$topic}] does not exist.");
        }

        $hydrator = match (true) {
            method_exists($topic, 'fromPayload') => 'fromPayload',
            method_exists($topic, 'fromPrimitives') => 'fromPrimitives',
            default => throw new RuntimeException("Outbox event type [{$topic}] has no supported hydrator."),
        };

        $event = (new ReflectionMethod($topic, $hydrator))->invoke(null, $data);

        if (! is_object($event)) {
            throw new RuntimeException("Outbox hydrator [{$topic}::{$hydrator}] did not return an event object.");
        }

        $this->tenantContext->run(
            $envelope,
            $event,
            fn (): ?array => $this->events->dispatch($event),
        );
    }
}
