<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Contracts\Events\DomainEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\Money;
use App\ValueObjects\OrderNumber;
use App\ValueObjects\TenantId;
use DateTimeImmutable;

/**
 * OrderPaid - Pure Domain Event for Payment Confirmation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - The order exists and is in a valid state for payment              ║
 * ║    - The payment amount matches the order total                        ║
 * ║    - This event is only dispatched AFTER the transaction commits       ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Eloquent models                                              ║
 * ║    - ❌ NO SerializesModels trait                                       ║
 * ║    - ❌ NO ShouldBroadcast (moved to OrderPaidBroadcast)               ║
 * ║    - ❌ NO Dispatchable trait (use DomainEventBus)                     ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - GenerateInvoicePdfListener
 *   - SendPaymentReceiptEmailListener
 *   - UpdateRevenueAnalyticsListener
 *   - NotifyFulfillmentTeamListener
 *   - → OrderPaidBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: PaymentService::confirmPayment() via DomainEventBus
 */
final readonly class OrderPaid implements DomainEvent
{
    use HasDomainMetadata;

    /**
     * @param TenantId $tenantId Tenant context for queue middleware
     * @param int $orderId The order aggregate root ID
     * @param OrderNumber $orderNumber Human-readable order reference
     * @param string $transactionId Payment gateway transaction ID
     * @param string $paymentMethod Payment method used (e.g., 'card', 'transfer')
     * @param Money $amountPaid The exact amount paid (Value Object)
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public string $transactionId,
        public string $paymentMethod,
        public Money $amountPaid,
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
        string $transactionId,
        string $paymentMethod,
        int $amountCents,
        string $currency = 'HNL'
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            transactionId: $transactionId,
            paymentMethod: $paymentMethod,
            amountPaid: Money::fromCents($amountCents, $currency),
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
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantId->toInt(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'transaction_id' => $this->transactionId,
            'payment_method' => $this->paymentMethod,
            'amount_cents' => $this->amountPaid->amount(),
            'currency' => $this->amountPaid->currency(),
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Defensive deserialization: fail fast on missing tenant_id
        $tenantId = $primitives['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            tenantId: TenantId::fromInt($tenantId),
            orderId: $primitives['order_id'] ?? 0,
            orderNumber: OrderNumber::fromString($primitives['order_number'] ?? 'UNKNOWN'),
            transactionId: $primitives['transaction_id'] ?? '',
            paymentMethod: $primitives['payment_method'] ?? 'unknown',
            amountPaid: Money::fromCents(
                $primitives['amount_cents'] ?? 0,
                $primitives['currency'] ?? 'HNL'
            ),
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
