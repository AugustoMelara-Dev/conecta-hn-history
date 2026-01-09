<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Contracts\Events\DomainEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\OrderNumber;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * OrderDelivered - Pure Domain Event for Delivery Completion
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - The order was in a ready state (cooked/packed)                     ║
 * ║    - Customer has received the order                                    ║
 * ║    - This event is only dispatched AFTER the transaction commits       ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO SerializesModels trait                                       ║
 * ║    - ❌ NO ShouldBroadcast (use separate Broadcast event)              ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ║  LISTENERS:                                                             ║
 * ║    - SendDeliveryConfirmationEmailListener                              ║
 * ║    - RequestReviewListener                                              ║
 * ║    - → OrderDeliveredBroadcastListener (fires UI event)                 ║
 * ║  ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class OrderDelivered implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        // Restoration parameters
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
     * Factory: Create from order data.
     */
    public static function create(
        int $tenantId,
        int $orderId,
        string $orderNumber
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
        );
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
    // Safe Serialization
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId->toInt(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        $tenantId = $primitives['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            tenantId: TenantId::fromInt($tenantId),
            orderId: $primitives['order_id'] ?? 0,
            orderNumber: OrderNumber::fromString($primitives['order_number'] ?? 'UNKNOWN'),
            // Restoration parameters
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
