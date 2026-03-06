<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
// use App\Models\Order; // REMOVED
use App\Traits\HasDomainMetadata;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * OrderCreated - Domain Event with Transactional Outbox Support
 *
 * Fired when a new Order is successfully placed and persisted.
 * This event is stored in the Outbox for guaranteed at-least-once delivery.
 */
final readonly class OrderCreated implements DomainEvent, ShouldBeStoredInOutbox, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $orderId,
        public string $tenantId,
        public string $orderNumber,
        public string $customerName,
        public int $total,
        public string $status,
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
        // Assuming TenantId has fromString now, or we implement it.
        // If tenantId is int in DB, we cast to string.
        return TenantId::fromString($this->tenantId);
    }

    public function aggregateId(): string
    {
        return (string) $this->orderId;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OUTBOX INTERFACE IMPLEMENTATION
    // ═══════════════════════════════════════════════════════════════════════

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'order_id' => $this->orderId,
            'tenant_id' => $this->tenantId,
            'order_number' => $this->orderNumber,
            'customer_name' => $this->customerName,
            'total' => $this->total,
            'status' => $this->status,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            orderId: (int) $payload['order_id'],
            tenantId: (string) $payload['tenant_id'],
            orderNumber: (string) $payload['order_number'],
            customerName: (string) ($payload['customer_name'] ?? 'Guest'),
            total: (int) $payload['total'],
            status: (string) ($payload['status'] ?? 'pending'),
            restoredEventId: $payload['event_id'] ?? null,
            restoredOccurredAt: $payload['occurred_at'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null
        );
    }

    public function getAggregateId(): ?string
    {
        return (string) $this->orderId;
    }

    public function getAggregateType(): ?string
    {
        return 'order'; // Decoupled from class name
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
