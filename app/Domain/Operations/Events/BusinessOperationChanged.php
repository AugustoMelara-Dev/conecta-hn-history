<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Versioned, provider-neutral integration event for operational aggregates.
 */
final readonly class BusinessOperationChanged
{
    /** @param array<string, scalar|null> $attributes */
    public function __construct(
        public string $aggregateId,
        public string $aggregateType,
        public string $tenantId,
        public string $actorId,
        public string $eventName,
        public array $attributes,
        public CarbonImmutable $occurredAt,
        public string $schemaVersion = '1.0',
    ) {}

    public function occurredOn(): DateTimeInterface
    {
        return $this->occurredAt;
    }
}
