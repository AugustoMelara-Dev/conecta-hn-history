<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Products;

use App\Domain\Product\Repositories\ProductBlurhashWriter;
use App\Models\Product;

final class EloquentProductBlurhashWriter implements ProductBlurhashWriter
{
    public function updateBlurhash(string $tenantId, int $productId, string $blurhash): bool
    {
        // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        return Product::withoutGlobalScopes()
            ->whereKey($productId)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('image_path')
            ->update(['blur_hash' => $blurhash]) === 1;
    }
}
