<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;
use DateTimeImmutable;

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
 * Implements DomainEvent (canonical) via Bridge (W-01).
 * Uses HasEventIdentity (W-03).
 */
final readonly class UserDeleted implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

    private const EVENT_NAME = 'identity.user_deleted';

    private const SCHEMA_VERSION = 3; // Bumped: PII removed (A6/GDPR compliance)

    public function __construct(
        public string $userId,
        public string $tenantId,
        public DateTimeImmutable $deletedAt,
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

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

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function tenantId(): mixed
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    public function eventName(): string
    {
        return self::EVENT_NAME;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            userId: (string) ($payload['user_id'] ?? ''),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            deletedAt: isset($payload['deleted_at'])
                ? new DateTimeImmutable($payload['deleted_at'])
                : new DateTimeImmutable,
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'deleted_at' => $this->deletedAt->format(DateTimeImmutable::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
