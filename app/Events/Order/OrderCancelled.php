<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Contracts\Events\DomainEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\OrderNumber;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * OrderCancelled - Pure Domain Event for Order Voiding
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - The order was in a cancellable state (not delivered)              ║
 * ║    - Inventory restoration is triggered                                 ║
 * ║    - Refund is initiated if payment was received                       ║
 * ║    - This event is only dispatched AFTER the transaction commits       ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO ShouldBroadcast (moved to OrderCancelledBroadcast)          ║
 * ║    - ❌ NO Dispatchable trait (use DomainEventBus)                     ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ║                                                                          ║
 * ║  CANCELLATION SOURCES:                                                  ║
 * ║    - Customer request                                                   ║
 * ║    - Merchant decision (out of stock)                                  ║
 * ║    - System (payment timeout via ReleaseStaleOrders job)               ║
 * ║    - Fraud detection                                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - SendCancellationEmailListener
 *   - RestoreInventoryListener
 *   - InitiateRefundListener
 *   - UpdateAnalyticsListener
 *   - → OrderCancelledBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: OrderStateService::onCancelled() via DomainEventBus
 */
final readonly class OrderCancelled implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

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
        $this->initMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
    }

    /**
     * Factory: Create from order data (snapshotted at creation time).
     */
    public static function create(
        int $tenantId,
        int $orderId,
        string $orderNumber,
        string $reason,
        ?int $cancelledById = null,
        bool $wasPaid = false
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
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

    // ═══════════════════════════════════════════════════════════════════════
    // DomainEvent Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function aggregateId(): int
    {
        return $this->orderId;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
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
        // ═══════════════════════════════════════════════════════════════════
        // FAIL-FAST VALIDATION: Critical Fields Must Exist
        // ═══════════════════════════════════════════════════════════════════
        // RATIONALE: The 'was_paid' field determines whether a refund should
        // be initiated. Defaulting to 'false' when missing causes paid orders
        // to NOT receive refunds - a critical business logic failure.
        // ═══════════════════════════════════════════════════════════════════

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
            tenantId: TenantId::fromInt($primitives['tenant_id']),
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
