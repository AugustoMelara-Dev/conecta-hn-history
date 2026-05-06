<?php

declare(strict_types=1);

namespace App\Domain\Auth\Events;

use DateTimeImmutable;

/**
 * UserPasswordChanged - Security-Critical Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IMMUTABLE: final readonly ensures event integrity for replay/audit.    ║
 * ║  TENANT AWARE: Carries tenant context for multi-tenant listeners.       ║
 * ║  SCHEMA V1: Validates against user_password_changed.v1.json             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UserPasswordChanged
{
    private const SCHEMA_VERSION = 1;

    private const EVENT_NAME = 'auth.user_password_changed';

    public function __construct(
        /** User whose password was changed (UUID string) */
        public string $userId,

        /** Tenant context for multi-tenant routing */
        public string $tenantId,

        /**
         * Indicates if remember_token was rotated (sessions invalidated).
         * CRITICAL for security audit: false means old sessions may persist.
         */
        public bool $sessionInvalidated,

        /** When the password change occurred */
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Factory method for consistent event creation.
     */
    public static function now(
        string $userId,
        string $tenantId,
        bool $sessionInvalidated,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            sessionInvalidated: $sessionInvalidated,
            occurredAt: new DateTimeImmutable,
        );
    }

    /**
     * Canonical event type for serialization/routing.
     */
    public function eventName(): string
    {
        return self::EVENT_NAME;
    }

    /**
     * Schema version for contract validation.
     */
    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Serialize to primitive payload for storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'session_invalidated' => $this->sessionInvalidated,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Hydrate from storage payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            userId: $payload['user_id'],
            tenantId: $payload['tenant_id'],
            sessionInvalidated: $payload['session_invalidated'],
            occurredAt: new DateTimeImmutable($payload['occurred_at']),
        );
    }
}
