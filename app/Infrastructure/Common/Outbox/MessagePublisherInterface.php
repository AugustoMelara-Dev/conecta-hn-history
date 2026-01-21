<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Outbox;

/**
 * MessagePublisherInterface - The Transport Contract
 *
 * Decouples the Outbox mechanism from the specific message broker
 * (Redis, RabbitMQ, Kafka, Log).
 */
interface MessagePublisherInterface
{
    /**
     * Publish a message to the external bus.
     *
     * @param  string  $topic  The channel/topic name (usually Event FQCN).
     * @param  string  $payload  The raw JSON content.
     */
    public function publish(string $topic, string $payload): void;
}
