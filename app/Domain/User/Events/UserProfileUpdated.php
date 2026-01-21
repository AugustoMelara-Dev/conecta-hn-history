<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * UserProfileUpdated - Profile Change Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IMMUTABLE: final readonly ensures event integrity for replay/audit.    ║
 * ║  TENANT AWARE: Carries tenant context for multi-tenant listeners.       ║
 * ║  SCHEMA V1: Validates against user_profile_updated.v1.json              ║
 * ║  V2 MIGRATION: Supports both 'user.profile_updated' (Canonical) and     ║
 * ║                'auth.user_profile_updated' (Legacy) via Alias.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UserProfileUpdated
{
    // use Dispatchable, InteractsWithSockets, SerializesModels; // Removed to keep it pure Domain Event DTO

    private const SCHEMA_VERSION = 1;

    /** Canonical V2 Name */
    public const NAME = 'user.profile_updated';

    /** Historical V1 Name (for Upcasting/Aliasing) */
    public const LEGACY_NAME = 'auth.user_profile_updated';

    public function __construct(
        /** User whose profile was updated (UUID string) */
        public string $userId,

        /** Tenant context for multi-tenant routing */
        public string $tenantId,

        /**
         * Indicates if email address was changed.
         * CRITICAL: Listeners should trigger email re-verification if true.
         */
        public bool $emailChanged,

        /** When the profile update occurred */
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Factory method for consistent event creation.
     */
    public static function now(
        string $userId,
        string $tenantId,
        bool $emailChanged,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            emailChanged: $emailChanged,
            occurredAt: new DateTimeImmutable,
        );
    }

    /**
     * Canonical event type for serialization/routing.
     */
    public function eventName(): string
    {
        return self::NAME;
    }

    /**
     * Broadcast Identity (Laravel Queue/Websocket Name).
     */
    public function broadcastAs(): string
    {
        return self::NAME;
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
            'email_changed' => $this->emailChanged,
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
            emailChanged: $payload['email_changed'],
            occurredAt: new DateTimeImmutable($payload['occurred_at']),
        );
    }
}
