<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use App\ValueObjects\OrderNumber;

/**
 * OrderCancelled - Pure Domain Event for Order Voiding
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  PURE DOMAIN EVENT: Business Fact Only                                  â•‘
 * â• â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•£
 * â•‘                                                                          â•‘
 * â•‘  INVARIANTS GUARANTEED:                                                 â•‘
 * â•‘    - The order was in a cancellable state (not delivered)              â•‘
 * â•‘    - Inventory restoration is triggered                                 â•‘
 * â•‘    - Refund is initiated if payment was received                       â•‘
 * â•‘    - This event is only dispatched AFTER the transaction commits       â•‘
 * â•‘                                                                          â•‘
 * â•‘  PURITY RULES:                                                          â•‘
 * â•‘    - âŒ NO Eloquent models                                              â•‘
 * â•‘    - âŒ NO ShouldBroadcast (moved to OrderCancelledBroadcast)          â•‘
 * â•‘    - âŒ NO Dispatchable trait (use DomainEventBus)                     â•‘
 * â•‘    - âœ… Only primitives and Value Objects                               â•‘
 * â•‘    - âœ… toPrimitives() for safe queue serialization                     â•‘
 * â•‘    - âœ… eventId() for idempotency                                       â•‘
 * â•‘                                                                          â•‘
 * â•‘  CANCELLATION SOURCES:                                                  â•‘
 * â•‘    - Customer request                                                   â•‘
 * â•‘    - Merchant decision (out of stock)                                  â•‘
 * â•‘    - System (payment timeout via ReleaseStaleOrders job)               â•‘
 * â•‘    - Fraud detection                                                    â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 *
 * LISTENERS:
 *   - SendCancellationEmailListener
 *   - RestoreInventoryListener
 *   - InitiateRefundListener
 *   - UpdateAnalyticsListener
 *   - â†’ OrderCancelledBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: OrderStateService::onCancelled() via DomainEventBus
 */
final readonly class OrderCancelled implements DomainEvent
{
    use HasDomainMetadata;

    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  int  $orderId  The order aggregate root ID
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  string  $reason  Machine or human-readable cancellation reason
     * @param  int|null  $cancelledById  User ID who initiated cancellation (null for system)
     * @param  bool  $wasPaid  Whether the order was paid (affects refund logic)
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public string $reason,
        public ?int $cancelledById,
        public bool $wasPaid,
        // Restoration parameters for fromPrimitives()
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null,
        ?string $restoredCorrelationId = null,
    ) {
        $metadata = self::resolveMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
        $this->eventId = $metadata['eventId'];
        $this->correlationId = $metadata['correlationId'];
        $this->occurredAt = $metadata['occurredAt'];
    }

    /**
     * Factory: Create from order data (snapshotted at creation time).
     */
    public static function create(
        string $tenantId,
        int $orderId,
        string $orderNumber,
        string $reason,
        ?int $cancelledById = null,
        bool $wasPaid = false
    ): self {
        return new self(
            tenantId: TenantId::fromString($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            reason: $reason,
            cancelledById: $cancelledById,
            wasPaid: $wasPaid,
        );
    }

    /**
     * Check if this was a system-initiated cancellation.
     */
    public function isSystemCancellation(): bool
    {
        return $this->cancelledById === null;
    }

    /**
     * Check if automatic refund should be processed.
     */
    public function shouldAutoRefund(): bool
    {
        return $this->wasPaid;
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // DomainEvent Interface Implementation
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return (string) $this->orderId;
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function toPrimitives(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantId->toString(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'reason' => $this->reason,
            'cancelled_by_id' => $this->cancelledById,
            'was_paid' => $this->wasPaid,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
        // FAIL-FAST VALIDATION: Critical Fields Must Exist
        // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
        // RATIONALE: The 'was_paid' field determines whether a refund should
        // be initiated. Defaulting to 'false' when missing causes paid orders
        // to NOT receive refunds - a critical business logic failure.
        // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

        $requiredKeys = [
            'tenant_id',
            'order_id',
            'order_number',
            'reason',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $primitives)) {
                throw \App\Exceptions\CorruptedDomainEventException::missingKey($key, self::class);
            }
            if ($primitives[$key] === null) {
                throw \App\Exceptions\CorruptedDomainEventException::nullValue($key, self::class);
            }
        }

        // was_paid is CRITICAL for refund logic - must be explicitly set
        if (! array_key_exists('was_paid', $primitives)) {
            throw \App\Exceptions\CorruptedDomainEventException::missingKey('was_paid', self::class);
        }

        return new self(
            tenantId: TenantId::fromString((string) $primitives['tenant_id']),
            orderId: $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            reason: $primitives['reason'],
            cancelledById: $primitives['cancelled_by_id'] ?? null, // nullable by design
            wasPaid: (bool) $primitives['was_paid'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
