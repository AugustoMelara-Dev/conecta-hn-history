<?php

declare(strict_types=1);

namespace App\Infrastructure\Product;

use App\Domain\Product\Contracts\ProductReader;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Infrastructure\Shared\EloquentPaginatedResult;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * EloquentProductReader - Eloquent Implementation of ProductReader
 *
 * ADAPTER: Implements the Domain contract using Eloquent ORM.
 * MULTI-TENANT: All queries explicitly scoped by tenant_id.
 */
final class EloquentProductReader implements ProductReader
{
    public function getInventory(int|string $tenantId): array
    {
        return Product::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function getLowStockProducts(int|string $tenantId): array
    {
        return Product::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->whereIn('stock_status', ['low_stock', 'out_of_stock'])
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function search(int|string $tenantId, array $filters, int $perPage = 20): PaginatedResult
    {
        return new EloquentPaginatedResult(
            Product::query()
                ->where('tenant_id', (string) $tenantId)
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
                ->paginate($perPage)
        );
    }

    public function find(int|string $tenantId, int $productId): ?object
    {
        return Product::where('tenant_id', (string) $tenantId)
            ->where('id', $productId)
            ->where('is_active', true)
            ->with(['category', 'images'])
            ->first();
    }
}
