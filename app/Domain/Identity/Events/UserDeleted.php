<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * UserDeleted - Digital Tombstone Domain Event
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GDPR COMPLIANCE: Captures user snapshot BEFORE deletion for cleanup.   ║
 * ║  PRIMITIVES ONLY: No Eloquent models - prevents ModelNotFoundException. ║
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
    public function __construct(
        /** Historical user ID (no longer in DB) */
        public int $userId,
        
        /** Tenant context for cleanup scope */
        public int $tenantId,
        
        /** Email for suppression lists and farewell notifications */
        public string $email,
        
        /** User's display name for audit purposes */
        public string $name,
        
        /** ISO-8601 timestamp of deletion moment */
        public string $deletedAt,
    ) {}

    /**
     * Create from user data snapshot.
     *
     * @param int $userId
     * @param int $tenantId
     * @param string $email
     * @param string $name
     * @return self
     */
    public static function fromSnapshot(
        int $userId,
        int $tenantId,
        string $email,
        string $name,
    ): self {
        return new self(
            userId: $userId,
            tenantId: $tenantId,
            email: $email,
            name: $name,
            deletedAt: now()->toIso8601String(),
        );
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
            'deleted_at' => $this->deletedAt,
        ];
    }
}
