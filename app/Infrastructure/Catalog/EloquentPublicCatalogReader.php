<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Catalog\Contracts\PublicCatalogReader;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * EloquentPublicCatalogReader - Implementation
 */
final class EloquentPublicCatalogReader implements PublicCatalogReader
{
    public function getFeaturedProducts(int $limit = 6): array
    {
        return Product::where('is_active', true)
            ->where('is_visible', true)
            ->whereHas('tenant', static function ($query): void {
                $query
                    ->where('status', 'approved')
                    ->where('is_active', true)
                    ->whereIn('subscription_status', ['active', 'trial']);
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->with(['category', 'tenant'])
            ->get()
            ->toArray();
    }

    public function getLocations(): array
    {
        return $this->publishedTenants()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['id', 'name', 'slug', 'latitude', 'longitude', 'address', 'category_id'])
            ->with('category')
            ->get()
            ->toArray();
    }

    public function getDirectoryListing(): array
    {
        return $this->publishedTenants()
            ->orderBy('name')
            ->with(['category', 'city'])
            ->get()
            ->toArray();
    }

    private function publishedTenants(): Builder
    {
        return Tenant::query()->published();
    }
}
