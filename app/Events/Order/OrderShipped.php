<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use App\ValueObjects\OrderNumber;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * OrderShipped - Pure Domain Event for Fulfillment Completion
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - The order was in a shippable state (paid, not cancelled)          ║
 * ║    - Tracking number is valid for the carrier                          ║
 * ║    - This event is only dispatched AFTER the transaction commits       ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO SerializesModels trait                                       ║
 * ║    - ❌ NO ShouldBroadcast (use separate Broadcast event)              ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ║    - ✅ correlationId() for distributed tracing                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - SendTrackingEmailListener
 *   - SendShipmentSmsListener
 *   - UpdateDeliveryEstimateListener
 *   - SyncToShippingCarrierListener
 *   - → OrderShippedBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: FulfillmentService::markAsShipped() via DomainEventBus
 */
final readonly class OrderShipped implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  int  $orderId  The order aggregate root ID
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  string  $carrier  Shipping carrier name (e.g., 'fedex', 'dhl')
     * @param  string  $trackingNumber  Carrier tracking number
     * @param  string|null  $estimatedDelivery  ISO date of estimated delivery
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public string $carrier,
        public string $trackingNumber,
        public ?string $estimatedDelivery,
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
     * Factory: Create from shipping data (snapshotted at creation time).
     */
    public static function create(
        string $tenantId,
        int $orderId,
        string $orderNumber,
        string $carrier,
        string $trackingNumber,
        ?string $estimatedDelivery = null
    ): self {
        return new self(
            tenantId: TenantId::fromString($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            carrier: $carrier,
            trackingNumber: $trackingNumber,
            estimatedDelivery: $estimatedDelivery,
        );
    }

    /**
     * Get carrier name for external URL resolution.
     * NOTE: URL generation is delegated to Listeners (Infrastructure layer).
     */
    public function carrier(): string
    {
        return $this->carrier;
    }

    /**
     * Get tracking number for external URL resolution.
     */
    public function trackingNumber(): string
    {
        return $this->trackingNumber;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DomainEvent Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return (string) $this->orderId;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId->toString(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
            'estimated_delivery' => $this->estimatedDelivery,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Defensive deserialization: handle missing/corrupt keys gracefully
        $tenantId = $primitives['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            tenantId: TenantId::fromString((string) $tenantId),
            orderId: $primitives['order_id'] ?? 0,
            orderNumber: OrderNumber::fromString($primitives['order_number'] ?? 'UNKNOWN'),
            carrier: $primitives['carrier'] ?? 'unknown',
            trackingNumber: $primitives['tracking_number'] ?? '',
            estimatedDelivery: $primitives['estimated_delivery'] ?? null,
            // Restoration parameters
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
