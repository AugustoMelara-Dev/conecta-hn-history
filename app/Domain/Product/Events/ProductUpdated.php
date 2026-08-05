<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * ProductUpdated - Domain Event for Product Update
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class ProductUpdated implements ShouldDispatchAfterCommit
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
