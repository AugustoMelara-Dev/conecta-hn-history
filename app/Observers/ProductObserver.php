<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\CatalogPublishedChanged;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
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
        // Surgical cache clearing using Tags
        Cache::tags(['products', "products_tenant_{$product->tenant_id}"])->flush();

        CatalogPublishedChanged::dispatch(
            tenantId: (string) $product->tenant_id,
            productId: (int) $product->getKey(),
            source: 'product_observer',
        );
    }
}
