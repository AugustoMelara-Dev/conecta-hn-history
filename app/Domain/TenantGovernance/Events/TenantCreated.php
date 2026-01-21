<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\Domain\Common\Contracts\DomainEventInterface;
use DateTimeImmutable;

/**
 * TenantCreated Event - Domain Fact
 *
 * Immutable record of a Tenant's birth.
 */
final class TenantCreated implements DomainEventInterface
{
    public readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $domain,
        ?DateTimeImmutable $occurredOn = null
    ) {
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function aggregateId(): string
    {
        return $this->id;
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            domain: (string) ($payload['domain'] ?? ''),
            occurredOn: isset($payload['occurred_on'])
                ? new DateTimeImmutable($payload['occurred_on'])
                : new DateTimeImmutable
        );
    }
}
