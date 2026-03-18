<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Contracts\TenantAwareEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;

/**
 * ProductPriceChanged - Event fired when a product price is updated.
 */
final readonly class ProductPriceChanged implements DomainEvent, ShouldDispatchAfterCommit, TenantAwareEvent
{
    use HasDomainMetadata;

    public function __construct(
        private TenantId $tenantId,
        public int $productId,
        public int $oldPrice,
        public int $newPrice,
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
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return (string) $this->productId;
    }

    public function toPrimitives(): array
    {
        return [
            'tenant_id' => $this->tenantId->toString(),
            'product_id' => $this->productId,
            'old_price' => $this->oldPrice,
            'new_price' => $this->newPrice,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            tenantId: TenantId::fromString((string) $primitives['tenant_id']),
            productId: $primitives['product_id'],
            oldPrice: $primitives['old_price'],
            newPrice: $primitives['new_price'],
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null
        );
    }
}
