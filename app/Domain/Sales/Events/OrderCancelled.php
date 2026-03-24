<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use App\ValueObjects\OrderNumber;

/**
 * OrderCancelled - Pure domain event for order cancellation.
 *
 * This event snapshots the state needed to restore inventory, trigger refunds,
 * update analytics, and notify the UI safely after cancellation.
 *
 * Listeners:
 * - SendCancellationEmailListener
 * - RestoreInventoryListener
 * - InitiateRefundListener
 * - UpdateAnalyticsListener
 * - OrderCancelledBroadcastListener
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
     * @param  int|null  $cancelledById  User ID who initiated cancellation
     * @param  bool  $wasPaid  Whether the order was paid
     */
    public function __construct(
        private TenantId $tenantId,
        private int $orderId,
        public OrderNumber $orderNumber,
        public string $reason,
        public ?int $cancelledById,
        public bool $wasPaid,
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
     * Factory: create from order data captured at cancellation time.
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

    // DomainEvent interface

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return (string) $this->orderId;
    }

    // Safe serialization

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
        // Fail fast on missing values because refund behavior depends on them.
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

        if (! array_key_exists('was_paid', $primitives)) {
            throw \App\Exceptions\CorruptedDomainEventException::missingKey('was_paid', self::class);
        }

        return new self(
            tenantId: TenantId::fromString((string) $primitives['tenant_id']),
            orderId: $primitives['order_id'],
            orderNumber: OrderNumber::fromString($primitives['order_number']),
            reason: $primitives['reason'],
            cancelledById: $primitives['cancelled_by_id'] ?? null,
            wasPaid: (bool) $primitives['was_paid'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
