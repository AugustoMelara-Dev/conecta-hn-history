<?php

declare(strict_types=1);

namespace App\Contracts\Events;

use App\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * DomainEvent - Contract for Business Facts (Enterprise-Grade)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL INVARIANTS                                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  1. IMMUTABILITY: final readonly classes only                            ║
 * ║  2. ORM-AGNOSTIC: No Eloquent models, only primitives + VOs             ║
 * ║  3. TRANSACTION SAFETY: Dispatched after commit                         ║
 * ║  4. MULTI-TENANT: TenantId required for queue context                   ║
 * ║  5. IDEMPOTENCY: eventId for deduplication                              ║
 * ║  6. SERIALIZATION: toPrimitives() for queue safety                      ║
 * ║                                                                          ║
 * ║  WHY toPrimitives()?                                                    ║
 * ║    If you refactor a Value Object (rename, change namespace) while     ║
 * ║    there are jobs in Redis, workers will fail with "IncompleteClass".  ║
 * ║    toPrimitives() serializes to flat JSON, decoupling class structure. ║
 * ║                                                                          ║
 * ║  WHY eventId()?                                                         ║
 * ║    Queue delivery is "at-least-once". Without unique ID, retries       ║
 * ║    can cause duplicate invoices, double charges, spam emails.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface DomainEvent extends ShouldDispatchAfterCommit
{
    /**
     * Unique identifier for this specific event instance.
     * 
     * Used for idempotency checks in listeners:
     *   if (ProcessedEvent::exists($event->eventId())) return;
     */
    public function eventId(): string;

    /**
     * Get the tenant context for queue middleware context switching.
     * 
     * CRITICAL: This must be available BEFORE any model deserialization.
     */
    public function tenantId(): TenantId;

    /**
     * Get the aggregate root ID that this event relates to.
     */
    public function aggregateId(): int;

    /**
     * Get the exact moment this event occurred.
     * 
     * IMMUTABILITY: Must return DateTimeImmutable, never Carbon.
     */
    public function occurredAt(): DateTimeImmutable;

    /**
     * Serialize event to flat primitives (JSON-safe).
     * 
     * This decouples your PHP class structure from the queue message format.
     * Refactoring VOs won't break pending jobs.
     * 
     * @return array<string, scalar|null>
     */
    public function toPrimitives(): array;

    /**
     * Reconstruct event from primitives.
     * 
     * Used by consumers to deserialize from queue message.
     * 
     * @param array<string, mixed> $primitives
     */
    public static function fromPrimitives(array $primitives): self;
}
