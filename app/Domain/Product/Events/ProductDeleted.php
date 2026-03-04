<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ProductDeleted - Domain Event for Product Soft Deletion
 *
 * Signals that a product has been soft-deleted. Listeners may:
 * - Update search indexes
 * - Notify affected users
 * - Log audit trail
 */
final readonly class ProductDeleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $productId,
        public string $tenantId,
        public string $name,
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
