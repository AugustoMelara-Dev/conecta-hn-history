<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Events;

use App\Domain\Common\Contracts\DomainEventInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use DateTimeImmutable;

/**
 * TenantCreated Event - Domain Fact
 *
 * Immutable record of a Tenant's birth.
 */
final readonly class TenantCreated implements DomainEventInterface, ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 1;
    public function schemaVersion(): int { return self::SCHEMA_VERSION; }
    public function __construct(
        public string $id,
        public string $name,
        public string $domain,
        public DateTimeImmutable $occurredOn,
    ) {}

    public static function now(string $id, string $name, string $domain): self
    {
        return new self(
            id: $id,
            name: $name,
            domain: $domain,
            occurredOn: new DateTimeImmutable,
        );
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
                : new DateTimeImmutable,
        );
    }

    public function jsonSerialize(): mixed
    {
        return $this->toPayload();
    }
}
