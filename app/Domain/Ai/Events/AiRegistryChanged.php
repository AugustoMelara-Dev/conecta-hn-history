<?php

declare(strict_types=1);

namespace App\Domain\Ai\Events;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class AiRegistryChanged
{
    /** @param array<string, scalar|null> $attributes */
    public function __construct(
        public string $aggregateId,
        public string $tenantId,
        public string $actorId,
        public string $eventName,
        public string $reason,
        public array $attributes,
        public CarbonImmutable $occurredAt,
        public string $schemaVersion = '1.0',
    ) {}

    public function occurredOn(): DateTimeInterface
    {
        return $this->occurredAt;
    }
}
