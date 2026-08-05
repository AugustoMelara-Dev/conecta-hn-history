<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Products\ProductMediaCleanupQueue;
use App\Models\Product;
use App\Services\TenantCacheManager;
use InvalidArgumentException;

class ProductObserver
{
    public function __construct(
        private readonly TenantCacheManager $cacheKeys,
    ) {}

    /**
     * Handle the Product "saved" event.
     * Covers created and updated.
     */
    public function saved(Product $product): void
    {
        $this->clearCache($product);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->clearCache($product);
    }

    /**
     * Soft-deleted products retain media so restore remains lossless. A force
     * delete is the retention boundary and schedules canonical media cleanup.
     */
    public function forceDeleted(Product $product): void
    {
        $this->clearCache($product);

        if (! is_string($product->image_path) || $product->image_path === '') {
            return;
        }

        try {
            app(ProductMediaCleanupQueue::class)->enqueue(
                (string) $product->tenant_id,
                'public',
                $product->image_path,
                (int) $product->getKey(),
            );
        } catch (InvalidArgumentException $exception) {
            report($exception);
        }
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->clearCache($product);
    }

    /**
     * Invalidate tenant-specific product cache.
     */
    private function clearCache(Product $product): void
    {
        $this->cacheKeys->rotateTenantVersion((string) $product->tenant_id);
    }
}
