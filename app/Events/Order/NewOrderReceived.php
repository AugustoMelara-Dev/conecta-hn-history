<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Contracts\Events\DomainEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\Money;
use App\ValueObjects\OrderNumber;
use App\Domain\Shared\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * NewOrderReceived - Pure Domain Event for Order Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - A valid order was placed by a customer                             ║
 * ║    - All order items are in stock                                       ║
 * ║    - Payment is pending or completed                                    ║
 * ║    - This event is only dispatched AFTER the transaction commits       ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models (was using Order $order)                     ║
 * ║    - ❌ NO SerializesModels trait                                       ║
 * ║    - ❌ NO ShouldBroadcast (use separate Broadcast event)              ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ Snapshot data (itemCount, totalAmount) not full Order           ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ║    - ✅ correlationId() for distributed tracing                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - SendOrderConfirmationEmailListener
 *   - UpdateDashboardAnalyticsListener
 *   - NotifyMerchantListener
 *   - → NewOrderBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: OrderService::createOrder() via DomainEventBus
 */
final readonly class NewOrderReceived implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  int  $orderId  The order aggregate root ID
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  int  $itemCount  Total number of items in order (snapshot)
     * @param  Money  $totalAmount  Order total amount (Value Object)
     * @param  int  $customerId  Customer who placed the order
     * @param  string  $customerName  Customer display name (safe for notifications)
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public int $itemCount,
        public Money $totalAmount,
        private int $customerId,
        public string $customerName,
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
        string $tenantId,
        int $orderId,
        string $orderNumber,
        int $itemCount,
        int $totalAmountCents,
        int $customerId,
        string $customerName,
        string $currency = 'HNL'
    ): self {
        return new self(
            tenantId: TenantId::fromString($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            itemCount: $itemCount,
            totalAmount: Money::fromCents($totalAmountCents, $currency),
            customerId: $customerId,
            customerName: $customerName,
        );
    }

    /**
     * Get customer ID for listeners that need to load full customer.
     */
    public function customerId(): int
    {
        return $this->customerId;
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
            'item_count' => $this->itemCount,
            'total_amount_cents' => $this->totalAmount->cents(),
            'currency' => $this->totalAmount->currency(),
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // ═══════════════════════════════════════════════════════════════════
        // FAIL-FAST VALIDATION: Prevent Silent Data Corruption
        // ═══════════════════════════════════════════════════════════════════
        $requiredKeys = [
            'tenant_id',
            'order_id',
            'order_number',
            'item_count',
            'total_amount_cents',
            'customer_id',
            'customer_name',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $primitives)) {
                throw \App\Exceptions\CorruptedDomainEventException::missingKey($key, self::class);
            }
            if ($primitives[$key] === null) {
                throw \App\Exceptions\CorruptedDomainEventException::nullValue($key, self::class);
            }
        }

        return new self(
            tenantId: TenantId::fromString((string) $primitives['tenant_id']),
            orderId: $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            itemCount: $primitives['item_count'],
            totalAmount: Money::fromCents(
                $primitives['total_amount_cents'],
                $primitives['currency'] ?? 'HNL'
            ),
            customerId: $primitives['customer_id'],
            customerName: $primitives['customer_name'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
