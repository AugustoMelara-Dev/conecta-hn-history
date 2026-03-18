<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * ProductDeleted - Domain Event for Product Soft Deletion
 *
 * Signals that a product has been soft-deleted. Listeners may:
 * - Update search indexes
 * - Notify affected users
 * - Log audit trail
 *
 * Implements DomainEvent (canonical) via Bridge pattern (W-01).
 * Uses HasEventIdentity (W-03) for auto-generated eventId/correlationId.
 */
final readonly class ProductDeleted implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $productId,
        public string $tenantId,
        public string $name,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $identity = self::resolveEventIdentity($restoredEventId, $restoredCorrelationId);
        $this->eventId = $identity['eventId'];
        $this->correlationId = $identity['correlationId'];
    }

    // â”€â”€â”€ DomainEvent interface â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function tenantId(): mixed
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return (string) $this->productId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }

    // â”€â”€â”€ Serialization â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            productId: (int) ($payload['product_id'] ?? 0),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable,
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }
}
