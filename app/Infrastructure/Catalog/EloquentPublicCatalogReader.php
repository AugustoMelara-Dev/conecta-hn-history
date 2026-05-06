<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Catalog\Contracts\PublicCatalogReader;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * EloquentPublicCatalogReader - Implementation
 */
final class EloquentPublicCatalogReader implements PublicCatalogReader
{
    public function getFeaturedProducts(int $limit = 6): Collection
    {
        return Product::where('is_active', true)
            // ->where('is_featured', true) // Assuming field exists, or logic
            ->orderByDesc('created_at')
            ->limit($limit)
            ->with(['category', 'tenant']) // Assuming relations needed for display
            ->get();
    }

    public function getLocations(): Collection
    {
        // For map display
        return Tenant::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['id', 'name', 'slug', 'latitude', 'longitude', 'address', 'category_id'])
            ->with('category')
            ->get();
    }

    public function getDirectoryListing(): Collection
    {
        return Tenant::where('is_active', true)
            ->orderBy('name')
            ->with(['category', 'city'])
            ->get();
    }
}
