<?php

declare(strict_types=1);

namespace App\Traits;

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
     * Initialize metadata with auto-generated or restored values.
     * Call this in the constructor for ALL events.
     *
     * @param string|null $correlationId Pass existing ID to continue a trace chain
     * @param string|null $restoredEventId Pass existing eventId when deserializing
     * @param string|null $restoredOccurredAt Pass ISO timestamp when deserializing
     */
    private function initMetadata(
        ?string $correlationId = null,
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null
    ): void {
        // If restoring from primitives, use provided values
        if ($restoredEventId !== null) {
            $this->eventId = $restoredEventId;
            $this->correlationId = $correlationId ?? $restoredEventId;
            $this->occurredAt = new DateTimeImmutable($restoredOccurredAt ?? 'now');
        } else {
            // New event: generate fresh metadata
            $this->eventId = (string) Str::uuid();
            $this->correlationId = $correlationId ?? (string) Str::uuid();
            $this->occurredAt = new DateTimeImmutable();
        }
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
