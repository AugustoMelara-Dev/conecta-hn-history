<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Models\Order;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * OrderCreated - Domain Event with Transactional Outbox Support
 *
 * Fired when a new Order is successfully placed and persisted.
 * This event is stored in the Outbox for guaranteed at-least-once delivery.
 */
final readonly class OrderCreated implements DomainEvent, ShouldBeStoredInOutbox, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    public function __construct(
        public Order $order,
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

    public function tenantId(): TenantId
    {
        return TenantId::fromInt($this->order->tenant_id);
    }

    public function aggregateId(): int
    {
        return $this->order->id;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OUTBOX INTERFACE IMPLEMENTATION
    // ═══════════════════════════════════════════════════════════════════════

    public function toPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'tenant_id' => $this->order->tenant_id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer_name,
            'total' => (float) $this->order->total,
            'status' => $this->order->status,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        $order = Order::find($payload['order_id']);

        if (! $order) {
            throw new \RuntimeException(
                "Failed to rehydrate OrderCreated event: Order {$payload['order_id']} not found."
            );
        }

        return new self(
            order: $order,
            restoredEventId: $payload['event_id'] ?? null,
            restoredOccurredAt: $payload['occurred_at'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null
        );
    }

    public function getAggregateId(): ?string
    {
        return (string) $this->order->id;
    }

    public function getAggregateType(): ?string
    {
        return Order::class;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LEGACY COMPATIBILITY
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }
}
