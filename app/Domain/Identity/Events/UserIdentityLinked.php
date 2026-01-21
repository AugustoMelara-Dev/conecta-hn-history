<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;

/**
 * UserIdentityLinked - Domain Event
 *
 * Emitted when an existing user links a new social identity (e.g. Google, Facebook).
 * strictly POPO, final readonly.
 */
final readonly class UserIdentityLinked
{
    private const EVENT_NAME = 'identity.user_linked_social';
    private const SCHEMA_VERSION = 1;

    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $providerName,
        public string $providerId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function now(
        string $userId,
        string $tenantId,
        string $providerName,
        string $providerId,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            providerName: $providerName,
            providerId: $providerId,
            occurredAt: new DateTimeImmutable(),
        );
    }

    public function eventName(): string
    {
        return self::EVENT_NAME;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'provider_name' => $this->providerName,
            'provider_id' => $this->providerId,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
