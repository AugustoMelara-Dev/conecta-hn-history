<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use DateTimeImmutable;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * UserDeleted - Digital Tombstone Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GDPR COMPLIANCE (A6): PII ($email, $name) removed from event payload.  ║
 * ║  Events are stored indefinitely — PII must never persist in the log.    ║
 * ║  Consumers needing email/name must query the audit log before deletion. ║
 * ║  PRIMITIVES ONLY: No Eloquent models - prevents ModelNotFoundException. ║
 * ║  UUID ALIGNED: Uses string IDs consistent with UserEntity.              ║
 * ║  REACTIVE: Allows Billing, Notifications, Audit modules to react.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Use Cases:
 * - Billing subscription cancellation (userId + tenantId sufficient)
 * - Audit trail completion
 * - Resource cleanup (files, tokens, etc.)
 * - Marketing suppression: use userId to query suppression log pre-deletion
 */
final readonly class UserDeleted implements ShouldDispatchAfterCommit
{
    private const EVENT_NAME = 'identity.user_deleted';

    private const SCHEMA_VERSION = 3; // Bumped: PII removed (A6/GDPR compliance)

    public function __construct(
        /** Historical user ID (UUID string, no longer in DB) */
        public string $userId,

        /** Tenant context for cleanup scope (UUID string) */
        public string $tenantId,

        /** Timestamp of deletion moment */
        public DateTimeImmutable $deletedAt,
    ) {}

    /**
     * Create from user data snapshot.
     * NOTE: email/name intentionally excluded (A6 — GDPR compliance).
     */
    public static function fromSnapshot(
        string $userId,
        string $tenantId,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            deletedAt: new DateTimeImmutable,
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
            'deleted_at' => $this->deletedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
