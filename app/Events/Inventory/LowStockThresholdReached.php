<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Contracts\Events\DomainEvent;
use App\Traits\HasDomainMetadata;
use App\ValueObjects\TenantId;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * LowStockThresholdReached - Pure Domain Event for Inventory Alert
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN EVENT: Inventory Threshold Breach                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    Product stock_quantity falls at or below low_stock_threshold         ║
 * ║    after an order is placed.                                            ║
 * ║                                                                          ║
 * ║  PURITY RULES:                                                          ║
 * ║    - ❌ NO Product model (was using SerializesModels)                   ║
 * ║    - ❌ NO ShouldBroadcast (moved to LowStockBroadcast)                 ║
 * ║    - ✅ Only primitives and Value Objects                               ║
 * ║    - ✅ toPrimitives() for safe queue serialization                     ║
 * ║    - ✅ eventId() for idempotency                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * DISPATCH POINT: InventoryManager::decrementStock() via DomainEventBus
 */
final readonly class LowStockThresholdReached implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasDomainMetadata;

    /**
     * @param  TenantId  $tenantId  Tenant context for queue middleware
     * @param  int  $productId  The product aggregate root ID
     * @param  string  $productName  Human-readable product name (snapshotted)
     * @param  string|null  $productSku  Product SKU if available (snapshotted)
     * @param  int  $currentStock  Stock level after decrement
     * @param  int  $threshold  The low stock threshold configured for this product
     */
    public function __construct(
        private TenantId $tenantId,
        private int $productId,
        public string $productName,
        public ?string $productSku,
        public int $currentStock,
        public int $threshold,
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
     * Factory: Create from product data (snapshotted at creation time).
     */
    public static function create(
        int $tenantId,
        int $productId,
        string $productName,
        ?string $productSku,
        int $currentStock,
        int $threshold
    ): self {
        return new self(
            tenantId: TenantId::fromInt($tenantId),
            productId: $productId,
            productName: $productName,
            productSku: $productSku,
            currentStock: $currentStock,
            threshold: $threshold,
        );
    }

    /**
     * Check if stock is completely depleted.
     */
    public function isOutOfStock(): bool
    {
        return $this->currentStock === 0;
    }

    /**
     * Get the severity level for alerting.
     */
    public function severity(): string
    {
        if ($this->currentStock === 0) {
            return 'critical';
        }

        if ($this->currentStock <= $this->threshold / 2) {
            return 'warning';
        }

        return 'info';
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
        return $this->productId;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Safe Serialization (Queue-Safe, Refactor-Proof)
    // ═══════════════════════════════════════════════════════════════════════

    public function toPrimitives(): array
    {
        return [
            'event_id' => $this->eventId,
            'tenant_id' => $this->tenantId->toInt(),
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPrimitives(array $primitives): self
    {
        // Defensive deserialization: fail fast on missing tenant_id
        $tenantId = $primitives['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing required key: tenant_id');
        }

        return new self(
            tenantId: TenantId::fromInt($tenantId),
            productId: $primitives['product_id'] ?? 0,
            productName: $primitives['product_name'] ?? 'Unknown Product',
            productSku: $primitives['product_sku'] ?? null,
            currentStock: $primitives['current_stock'] ?? 0,
            threshold: $primitives['threshold'] ?? 0,
            restoredEventId: $primitives['event_id'] ?? null,
            restoredOccurredAt: $primitives['occurred_at'] ?? null,
            restoredCorrelationId: $primitives['correlation_id'] ?? null,
        );
    }
}
