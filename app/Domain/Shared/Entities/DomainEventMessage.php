<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entities;

use DateTimeImmutable;

/**
 * DomainEventMessage - Immutable Outbox Artifact
 *
 * Represents a domain event captured for asynchronous persistence.
 * This object is persisted transactionally with business state.
 */
final readonly class DomainEventMessage
{
    public function __construct(
        public string $eventId,
        public string $type,
        public string $aggregateId,
        public string $payload, // JSON serialized
        public DateTimeImmutable $occurredAt,
        public array $metadata = [],
    ) {}
}
