<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class LowStockThresholdReached implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;

    private string $eventId;

    private DateTimeImmutable $occurredAt;

    public function __construct(
        mixed $product,
        public int $currentStock,
        public int $threshold,
        int|string|null $tenantKey = null,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->productId = is_object($product) && method_exists($product, 'getKey') ? $product->getKey() : $product;
        $this->tenantKey = $tenantKey ?? ($product->tenant_id ?? 0);
        $this->eventId = $eventId ?? (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public int|string $productId;

    public int|string $tenantKey;

    public static function fromProduct(mixed $product, int $currentStock, int $threshold): self
    {
        return new self($product, $currentStock, $threshold);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString((string) $this->tenantKey);
    }

    public function aggregateId(): int
    {
        return (int) $this->productId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'product_id' => (string) $this->productId,
            'tenant_id' => (string) $this->tenantKey,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        return new self(
            product: (string) $primitives['product_id'],
            currentStock: (int) $primitives['current_stock'],
            threshold: (int) $primitives['threshold'],
            tenantKey: (string) $primitives['tenant_id'],
            eventId: isset($primitives['event_id']) ? (string) $primitives['event_id'] : null,
            occurredAt: isset($primitives['occurred_at']) ? new DateTimeImmutable((string) $primitives['occurred_at']) : null,
        );
    }
}
