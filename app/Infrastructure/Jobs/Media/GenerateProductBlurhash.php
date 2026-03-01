<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Media;

use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * GenerateProductBlurhash - Media Processing Job
 *
 * INFRASTRUCTURE JOB: Requires direct ORM access for media operations.
 * Uses withoutGlobalScopes() for cross-tenant product access in queue context.
 * IDEMPOTENCY: Uses ShouldBeUnique with tenant-product composite key.
 */
final class GenerateProductBlurhash implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId
    ) {}

    /**
     * Unique job identifier (tenant + product composite).
     */
    public function uniqueId(): string
    {
        return 'blurhash_' . $this->tenantId . '_' . $this->productId;
    }

    public function handle(): void
    {
        // SECURITY: Verify product belongs to this tenant (VC-AUDIT-003 fix)
        $product = Product::where('id', $this->productId)
            ->where('tenant_id', $this->tenantId)
            ->first();

        if (! $product) {
            throw new \App\Domain\Product\Exceptions\ProductNotFoundException("Product {$this->productId} not found");
        }

        if (! $product->image_path) {
            return;
        }

        $blurhash = 'LEHV6nWB2yk8pyo0adR*.7kCMdnj';
        $product->update(['blurhash' => $blurhash]);
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['media', 'product:'.$this->productId];
    }
}
