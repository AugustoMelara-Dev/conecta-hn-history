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
 * ║    - ✅ All IDs use STRING (UUID v7 compliant)                          ║
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
final readonly class OrderPaid implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  string  $orderId  The order aggregate root ID (UUID v7 string)
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  string  $transactionId  Payment gateway transaction ID
     * @param  string  $paymentMethod  Payment method used (e.g., 'card', 'transfer')
     * @param  Money  $amountPaid  The exact amount paid (Value Object)
     */
    public function __construct(
        private TenantId $tenantId,
        private string $orderId,
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
        string $tenantId,
        string $orderId,
        string $orderNumber,
        string $transactionId,
        string $paymentMethod,
        int $amountCents,
        string $currency = 'HNL'
    ): self {
        return new self(
            tenantId: TenantId::fromString((string) $tenantId),
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
            'tenant_id' => $this->tenantId->toString(),
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
        // ═══════════════════════════════════════════════════════════════════
        // FAIL-FAST VALIDATION: Prevent Silent Financial Data Corruption
        // ═══════════════════════════════════════════════════════════════════
        $requiredKeys = [
            'tenant_id',
            'order_id',
            'order_number',
            'transaction_id',
            'payment_method',
            'amount_cents',
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
            orderId: (string) $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            transactionId: $primitives['transaction_id'],
            paymentMethod: $primitives['payment_method'],
            amountPaid: Money::fromCents(
                $primitives['amount_cents'],
                $primitives['currency'] ?? 'HNL'
            ),
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
