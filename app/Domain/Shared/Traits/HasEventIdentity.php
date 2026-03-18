<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use Ramsey\Uuid\Uuid;

/**
 * HasEventIdentity — Minimal Event Identity Trait
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Provides auto-generated $eventId and $correlationId for Domain Events. ║
 * ║                                                                          ║
 * ║  DESIGN DECISION (W-03):                                                 ║
 * ║    Decoupled from HasDomainMetadata intentionally.                       ║
 * ║    HasDomainMetadata also manages $occurredAt via initMetadata().        ║
 * ║    This trait is for events that manage $occurredAt themselves            ║
 * ║    (e.g. constructor-promoted DateTimeImmutable $occurredAt).            ║
 * ║                                                                          ║
 * ║  FRAMEWORK-AGNOSTIC: Uses Ramsey\Uuid directly (not Illuminate\Str).    ║
 * ║  COMPATIBLE with `final readonly class` constructor-promoted $occurredAt.║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Usage pattern:
 *
 *   final readonly class MyEvent implements DomainEvent
 *   {
 *       use HasEventIdentity;
 *
 *       public function __construct(
 *           public string $myData,
 *           public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
 *           ?string $restoredEventId       = null,
 *           ?string $restoredCorrelationId = null,
 *       ) {
 *           $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
 *       }
 *   }
 */
trait HasEventIdentity
{
    /**
     * Unique event ID for idempotency checks.
     * Auto-generated via UUID v4 on construction unless restoring from payload.
     */
    public readonly string $eventId;

    /**
     * Correlation ID for distributed tracing across listeners.
     * Auto-generated via UUID v4 on construction unless restoring from payload.
     */
    public readonly string $correlationId;

    /**
     * Resolve event identity for assignment inside the event constructor.
     *
     * @return array{eventId: string, correlationId: string}
     */
    private static function resolveEventIdentity(
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ): array {
        return [
            'eventId' => $restoredEventId ?? Uuid::uuid4()->toString(),
            'correlationId' => $restoredCorrelationId ?? Uuid::uuid4()->toString(),
        ];
    }

    /**
     * Get the unique event identifier (satisfies DomainEvent::eventId()).
     */
    public function eventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get the correlation ID for distributed tracing (satisfies DomainEvent::correlationId()).
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }
}
