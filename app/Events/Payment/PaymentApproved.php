<?php

declare(strict_types=1);

namespace App\Events\Payment;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\Money;
use App\ValueObjects\OrderNumber;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * PaymentApproved - Pure Domain Event for Payment Confirmation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: PCI-DSS COMPLIANT                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHAT THIS EVENT CONTAINS:                                              ║
 * ║    ✅ transactionId: Opaque token from gateway (Stripe, BAC)            ║
 * ║    ✅ gatewayName: Which processor was used                             ║
 * ║    ✅ amountApproved: Money VO (cents + currency)                       ║
 * ║    ✅ orderNumber: Human-readable reference                             ║
 * ║                                                                          ║
 * ║  WHAT THIS EVENT MUST NEVER CONTAIN:                                    ║
 * ║    ❌ card_last_four                                                    ║
 * ║    ❌ card_brand                                                        ║
 * ║    ❌ holder_name                                                       ║
 * ║    ❌ cvv, expiry, or ANY card data                                     ║
 * ║    ❌ Eloquent models                                                   ║
 * ║                                                                          ║
 * ║  WHY: If this event goes to failed_jobs table or logs, we do NOT want  ║
 * ║  financial PII scattered across our infrastructure.                     ║
 * ║  FAILURE = BLOCK DEPLOYMENT                                             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - MarkOrderAsPaidListener
 *   - SendPaymentReceiptEmailListener
 *   - UpdateAccountingLedgerListener
 *   - TriggerFulfillmentWorkflowListener
 *
 * DISPATCH POINT: PaymentService::processPayment() via DomainEventBus
 */
final readonly class PaymentApproved implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  string  $orderId  The order aggregate root ID
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  string  $transactionId  Gateway's transaction token (NOT card data)
     * @param  string  $gatewayName  Payment processor (e.g., 'stripe', 'bac')
     * @param  Money  $amountApproved  The exact amount approved (Value Object)
     */
    public function __construct(
        private TenantId $tenantId,
        private string $orderId,
        public OrderNumber $orderNumber,
        public string $transactionId,
        public string $gatewayName,
        public Money $amountApproved,
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
     * Factory: Create from payment data (snapshotted at creation time).
     */
    public static function create(
        string $tenantId,
        int|string $orderId,
        string $orderNumber,
        string $transactionId,
        string $gatewayName,
        int $amountCents,
        string $currency = 'HNL'
    ): self {
        return new self(
            tenantId: TenantId::fromString((string) $tenantId),
            orderId: (string) $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            transactionId: $transactionId,
            gatewayName: $gatewayName,
            amountApproved: Money::fromCents($amountCents, $currency),
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

    /**
     * Serialize to flat primitives for safe queue transport.
     *
     * This decouples PHP class structure from queue message format.
     * Refactoring Money or TenantId VOs won't break pending jobs.
     */
    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantId->toString(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'transaction_id' => $this->transactionId,
            'gateway_name' => $this->gatewayName,
            'amount_cents' => $this->amountApproved->amount(),
            'currency' => $this->amountApproved->currency(),
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    /**
     * Reconstruct event from primitives (for queue consumers).
     */
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
            'gateway_name',
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
            tenantId: TenantId::fromSerialized($primitives['tenant_id']),
            orderId: (string) $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            transactionId: $primitives['transaction_id'],
            gatewayName: $primitives['gateway_name'],
            amountApproved: Money::fromCents(
                $primitives['amount_cents'],
                $primitives['currency'] ?? 'HNL'
            ),
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
