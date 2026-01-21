<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;

/**
 * UserRegisteredViaSocial - Domain Event
 *
 * Emitted when a NEW user is provisioned via Social Login (OAuth).
 * Critical for Welcome Emails, Audit, and Marketing synchronization.
 */
final readonly class UserRegisteredViaSocial
{
    private const EVENT_NAME = 'identity.user_registered_social';
    private const SCHEMA_VERSION = 1;

    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $email,
        public string $provider,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function now(
        string $userId,
        string $tenantId,
        string $email,
        string $provider,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            email: $email,
            provider: $provider,
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
            'email' => $this->email,
            'provider' => $this->provider,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
