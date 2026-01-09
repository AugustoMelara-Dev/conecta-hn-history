<?php

declare(strict_types=1);

namespace App\Infrastructure\Product;

use App\Domain\Product\Contracts\ProductReader;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * EloquentProductReader - Eloquent Implementation of ProductReader
 *
 * ADAPTER: Implements the Domain contract using Eloquent ORM.
 * MULTI-TENANT: All queries explicitly scoped by tenant_id.
 */
final class EloquentProductReader implements ProductReader
{
    public function getInventory(int $tenantId): Collection
    {
        return Product::withoutGlobalScopes()
            ->where('tenant_id', (int) $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function getLowStockProducts(int $tenantId): Collection
    {
        return Product::withoutGlobalScopes()
            ->where('tenant_id', (int) $tenantId)
            ->whereIn('stock_status', ['low_stock', 'out_of_stock'])
            ->orderBy('name')
            ->get();
    }

    public function search(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->where('tenant_id', (int) $tenantId)
            ->where('is_active', true)
            ->when($filters['search'] ?? null, function (Builder $query, string $term) {
                $query->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($filters['category'] ?? null, function (Builder $query, string $category) {
                $query->whereHas('category', function (Builder $q) use ($category) {
                    $q->where('slug', $category);
                });
            })
            ->with(['category:id,name,slug', 'images'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $tenantId, int $productId): ?Product
    {
        return Product::where('tenant_id', (int) $tenantId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->first();
    }
}
