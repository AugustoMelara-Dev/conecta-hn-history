<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Enums\LowStockSeverity;
use DateTimeImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

final readonly class LowStockThresholdReached implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;

    private string $eventId;

    private DateTimeImmutable $occurredAt;

    public function __construct(
        mixed $product,
        public int $currentStock,
        public int $threshold,
        ?string $tenantKey = null,
        ?string $productName = null,
        ?string $productSku = null,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $resolvedProductId = is_object($product) && method_exists($product, 'getKey')
            ? $product->getKey()
            : (is_object($product) ? self::productAttribute($product, 'id') : $product);

        if (! is_int($resolvedProductId) && ! is_string($resolvedProductId)) {
            throw new \InvalidArgumentException('Low-stock events require a scalar product identifier.');
        }

        $resolvedTenantKey = $tenantKey ?? (is_object($product) ? self::productAttribute($product, 'tenant_id') : null) ?? 0;

        if (! is_int($resolvedTenantKey) && ! is_string($resolvedTenantKey)) {
            throw new \InvalidArgumentException('Low-stock events require a scalar tenant identifier.');
        }

        $snapshotName = is_object($product) ? self::productAttribute($product, 'name') : null;
        $snapshotSku = is_object($product) ? self::productAttribute($product, 'sku') : null;

        $this->productId = $resolvedProductId;
        $this->tenantKey = (string) $resolvedTenantKey;
        $this->productName = $productName ?? (is_string($snapshotName) ? $snapshotName : "Product {$this->productId}");
        $this->productSku = $productSku ?? (is_string($snapshotSku) ? $snapshotSku : null);
        $this->eventId = $eventId ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public int|string $productId;

    public string $tenantKey;

    public string $productName;

    public ?string $productSku;

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

    public function aggregateId(): string
    {
        return (string) $this->productId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function severity(): string
    {
        return match (true) {
            $this->currentStock <= 0 => LowStockSeverity::Critical->value,
            $this->currentStock <= $this->threshold => LowStockSeverity::Warning->value,
            default => LowStockSeverity::Info->value,
        };
    }

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'product_id' => (string) $this->productId,
            'tenant_id' => (string) $this->tenantKey,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
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
            productName: isset($primitives['product_name']) ? (string) $primitives['product_name'] : null,
            productSku: isset($primitives['product_sku']) ? (string) $primitives['product_sku'] : null,
            eventId: isset($primitives['event_id']) ? (string) $primitives['event_id'] : null,
            occurredAt: isset($primitives['occurred_at']) ? new DateTimeImmutable((string) $primitives['occurred_at']) : null,
        );
    }

    private static function productAttribute(object $product, string $attribute): mixed
    {
        if (method_exists($product, 'getAttributes')) {
            $attributes = $product->getAttributes();

            return array_key_exists($attribute, $attributes) ? $attributes[$attribute] : null;
        }

        return property_exists($product, $attribute) ? $product->{$attribute} : null;
    }
}
