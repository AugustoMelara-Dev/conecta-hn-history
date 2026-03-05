<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Catalog\Contracts\PublicCatalogReader;
use App\Models\Product;
use App\Models\Tenant;

/**
 * EloquentPublicCatalogReader - Implementation
 */
final class EloquentPublicCatalogReader implements PublicCatalogReader
{
    public function getFeaturedProducts(int $limit = 6): array
    {
        return Product::where('is_active', true)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->with(['category', 'tenant'])
            ->get()
            ->toArray();
    }

    public function getLocations(): array
    {
        return Tenant::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['id', 'name', 'slug', 'latitude', 'longitude', 'address', 'category_id'])
            ->with('category')
            ->get()
            ->toArray();
    }

    public function getDirectoryListing(): array
    {
        return Tenant::where('is_active', true)
            ->orderBy('name')
            ->with(['category', 'city'])
            ->get()
            ->toArray();
    }
}
