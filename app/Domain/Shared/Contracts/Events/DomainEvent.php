<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts\Events;

use DateTimeImmutable;

/**
 * DomainEvent - Canonical Domain Contract
 *
 * W-06 P1 — Phase 4: Migrated from App\Contracts\Events\DomainEvent.
 * This is now the sole, authoritative DomainEvent interface.
 * App\Contracts\Events\DomainEvent has been deleted.
 *
 * All Domain Events MUST implement this interface.
 * All Infrastructure adapters (EventBus) depend ONLY on this contract.
 *
 * Migration complete:
 *   Phase 1 — Alias created (points to legacy)
 *   Phase 2 — Domain events migrated to this namespace
 *   Phase 3 — Infrastructure migrated to this namespace
 *   Phase 4 — Legacy deleted; this interface is now fully autonomous
 */
interface DomainEvent
{
    /**
     * Unique event ID for idempotency.
     */
    public function eventId(): string;

    /**
     * Correlation ID for distributed tracing.
     */
    public function correlationId(): string;

    /**
     * Tenant context for multi-tenant isolation.
     */
    public function tenantId(): mixed;

    /**
     * Aggregate root identifier.
     */
    public function aggregateId(): string;

    /**
     * Serialize to primitive array for Outbox / Queue transport.
     *
     * @return array<string, mixed>
     */
    public function toPrimitives(): array;

    /**
     * Reconstruct event from primitive array (queue consumer side).
     *
     * @param  array<string, mixed>  $primitives
     */
    public static function fromPrimitives(array $primitives): self;

    /**
     * Immutable timestamp when the event occurred.
     */
    public function occurredAt(): DateTimeImmutable;
}
