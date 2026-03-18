<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * HasDomainMetadata - Reduces Boilerplate for Domain Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Provides automatic generation of:                                      ║
 * ║    - eventId: UUID v4 for idempotency                                  ║
 * ║    - correlationId: UUID for distributed tracing                       ║
 * ║    - occurredAt: Immutable timestamp                                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHY CORRELATION ID?                                                    ║
 * ║    When OrderPaid triggers Invoice, Email, Analytics listeners,        ║
 * ║    ALL logs share the same correlationId for tracing:                  ║
 * ║                                                                          ║
 * ║    [corr_abc123] OrderPaid emitted                                      ║
 * ║    [corr_abc123] GenerateInvoiceListener started                       ║
 * ║    [corr_abc123] Invoice #INV-001 created                              ║
 * ║    [corr_abc123] SendReceiptEmail queued                               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * W-06 P2: Canonical location. Moved from App\Traits\HasDomainMetadata.
 * App\Traits\HasDomainMetadata kept as backward-compat alias for Infrastructure.
 *
 * NOTE:
 * Readonly metadata must be assigned directly inside each event constructor.
 * This trait only resolves metadata values for that constructor assignment.
 */
trait HasDomainMetadata
{
    /**
     * Unique event ID for idempotency checks.
     */
    public readonly string $eventId;

    /**
     * Correlation ID for distributed tracing.
     */
    public readonly string $correlationId;

    /**
     * Immutable timestamp when event occurred.
     */
    public readonly DateTimeImmutable $occurredAt;

    /**
     * Resolve metadata for assignment inside the event constructor.
     *
     * @return array{eventId: string, correlationId: string, occurredAt: DateTimeImmutable}
     */
    private static function resolveMetadata(
        ?string $correlationId = null,
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null
    ): array {
        // If restoring from primitives, use provided values
        if ($restoredEventId !== null) {
            return [
                'eventId' => $restoredEventId,
                'correlationId' => $correlationId ?? $restoredEventId,
                'occurredAt' => new DateTimeImmutable($restoredOccurredAt ?? 'now'),
            ];
        }

        return [
            'eventId' => (string) Str::uuid(),
            'correlationId' => $correlationId ?? (string) Str::uuid(),
            'occurredAt' => new DateTimeImmutable,
        ];
    }

    /**
     * Get the unique event identifier.
     */
    public function eventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get the correlation ID for distributed tracing.
     */
    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Get the immutable timestamp.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
