<?php

declare(strict_types=1);

namespace App\Domain\Ai\Events;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class AiHandoffChanged
{
    public function __construct(
        public string $aggregateId,
        public string $tenantId,
        public ?string $actorId,
        public string $conversationId,
        public string $messageId,
        public string $agentVersionId,
        public string $status,
        public int $version,
        public string $triggerType,
        public string $queueKey,
        public string $priority,
        public CarbonImmutable $occurredAt,
        public string $schemaVersion = '1.0',
    ) {}

    public function occurredOn(): DateTimeInterface
    {
        return $this->occurredAt;
    }
}
