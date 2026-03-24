<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;

/**
 * OrderCreated - Domain event with transactional outbox support.
 *
 * Fired when a new order is successfully placed and persisted.
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

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function aggregateId(): string
    {
        return (string) $this->orderId;
    }

    // Outbox serialization

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
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    public function getAggregateId(): string
    {
        return (string) $this->orderId;
    }

    public function getAggregateType(): string
    {
        return 'order';
    }

    // Legacy compatibility

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }
}
