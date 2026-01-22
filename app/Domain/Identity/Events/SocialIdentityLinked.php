<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;

/**
 * SocialIdentityLinked - Domain Event
 *
 * Emitted when an existing user links a new social identity.
 */
final readonly class SocialIdentityLinked
{
    private const EVENT_NAME = 'identity.social_identity_linked';

    private const SCHEMA_VERSION = 1;

    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $provider,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function now(
        string $userId,
        string $tenantId,
        string $provider,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            provider: $provider,
            occurredAt: new DateTimeImmutable,
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
            'provider' => $this->provider,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
