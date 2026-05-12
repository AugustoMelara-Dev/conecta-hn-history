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
 * OrderRefunded - Pure Domain Event for Refund Processing
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE DOMAIN EVENT: Business Fact Only                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  INVARIANTS GUARANTEED:                                                 ║
 * ║    - The order was in refundable state                                  ║
 * ║    - The refund amount does not exceed order total                      ║
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
 *   - SendRefundConfirmationEmailListener
 *   - UpdateRevenueAnalyticsListener
 *   - RestoreInventoryIfApplicableListener
 *   - → OrderRefundedBroadcastListener (fires UI event)
 *
 * DISPATCH POINT: RefundService::processRefund() via DomainEventBus
 */
final readonly class OrderRefunded implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  int  $orderId  The order aggregate root ID
     * @param  OrderNumber  $orderNumber  Human-readable order reference
     * @param  Money  $refundAmount  The exact refund amount (Value Object)
     * @param  string  $transactionId  Gateway refund transaction ID
     * @param  bool  $isPartial  Whether this is a partial refund
     * @param  string|null  $reason  Optional refund reason
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public Money $refundAmount,
        public string $transactionId,
        public bool $isPartial,
        public ?string $reason,
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
     * Factory: Create from refund data (snapshotted at creation time).
     */
    public static function create(
        int $tenantId,
        int $orderId,
        string $orderNumber,
        int $refundAmountCents,
        string $transactionId,
        bool $isPartial = false,
        ?string $reason = null,
        string $currency = 'HNL'
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
            orderId: $orderId,
            orderNumber: OrderNumber::fromString($orderNumber),
            refundAmount: Money::fromCents($refundAmountCents, $currency),
            transactionId: $transactionId,
            isPartial: $isPartial,
            reason: $reason,
        );
    }

    /**
     * Check if this was a partial refund.
     */
    public function isPartialRefund(): bool
    {
        return $this->isPartial;
    }

    /**
     * Check if this was a full refund.
     */
    public function isFullRefund(): bool
    {
        return ! $this->isPartial;
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
            'correlation_id' => $this->correlationId,
            'tenant_id' => $this->tenantId->toString(),
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber->toString(),
            'refund_amount_cents' => $this->refundAmount->cents(),
            'currency' => $this->refundAmount->currency(),
            'transaction_id' => $this->transactionId,
            'is_partial' => $this->isPartial,
            'reason' => $this->reason,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // ═══════════════════════════════════════════════════════════════════
        // FAIL-FAST VALIDATION: Critical Fields Must Exist
        // ═══════════════════════════════════════════════════════════════════
        // RATIONALE: Silent defaults (e.g., refund_amount_cents ?? 0) cause
        // data corruption. A refund of $0 is processed instead of failing
        // visibly. This is unacceptable for financial transactions.
        // ═══════════════════════════════════════════════════════════════════

        $requiredKeys = [
            'tenant_id',
            'order_id',
            'order_number',
            'refund_amount_cents',
            'transaction_id',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $primitives)) {
                throw \App\Exceptions\CorruptedDomainEventException::missingKey($key, self::class);
            }
            if ($primitives[$key] === null) {
                throw \App\Exceptions\CorruptedDomainEventException::nullValue($key, self::class);
            }
        }

        // is_partial has boolean semantics - missing is ambiguous, must be explicit
        if (! array_key_exists('is_partial', $primitives)) {
            throw \App\Exceptions\CorruptedDomainEventException::missingKey('is_partial', self::class);
        }

        return new self(
            tenantId: TenantId::fromInt($primitives['tenant_id']),
            orderId: $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            refundAmount: Money::fromCents(
                $primitives['refund_amount_cents'],
                $primitives['currency'] ?? 'HNL'
            ),
            transactionId: $primitives['transaction_id'],
            isPartial: (bool) $primitives['is_partial'],
            reason: $primitives['reason'] ?? null, // reason IS optional
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
