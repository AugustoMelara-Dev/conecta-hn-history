<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * UserDeleted - Digital Tombstone Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GDPR COMPLIANCE: Captures user snapshot BEFORE deletion for cleanup.   ║
 * ║  PRIMITIVES ONLY: No Eloquent models - prevents ModelNotFoundException. ║
 * ║  UUID ALIGNED: Uses string IDs consistent with UserEntity.              ║
 * ║  REACTIVE: Allows Billing, Notifications, Audit modules to react.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Use Cases:
 * - Marketing suppression lists
 * - Billing subscription cancellation
 * - Audit trail completion
 * - Resource cleanup (files, tokens, etc.)
 */
final readonly class UserDeleted implements ShouldDispatchAfterCommit
{
    private const EVENT_NAME = 'identity.user_deleted';
    private const SCHEMA_VERSION = 2; // Bumped for UUID migration

    public function __construct(
        /** Historical user ID (UUID string, no longer in DB) */
        public string $userId,
        
        /** Tenant context for cleanup scope (UUID string) */
        public string $tenantId,
        
        /** Email for suppression lists and farewell notifications */
        public string $email,
        
        /** User's display name for audit purposes */
        public string $name,
        
        /** Timestamp of deletion moment */
        public DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create from user data snapshot.
     */
    public static function fromSnapshot(
        string $userId,
        string $tenantId,
        string $email,
        string $name,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            email: $email,
            name: $name,
            deletedAt: new DateTimeImmutable(),
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

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'email' => $this->email,
            'name' => $this->name,
            'deleted_at' => $this->deletedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
