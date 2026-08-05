<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Domain\Catalog\Contracts\ProductCatalogReader;
use App\Models\Product;
use App\Services\TenantCacheManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * CachedProductReader - Infrastructure Implementation
 *
 * Responsibilities:
 * - Cache Management (Tags, TTL, Keys)
 * - Eloquent Query Construction
 */
final class CachedProductReader implements ProductCatalogReader
{
    private const TTL_SECONDS = 3600; // 1 Hour

    public function __construct(
        private readonly TenantCacheManager $cacheKeys,
    ) {}

    public function getFeaturedProducts(string $tenantId, int $limit = 10): Collection
    {
        return Cache::remember(
            $this->cacheKeys->makeKey($tenantId, "products_featured_limit_{$limit}"),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('is_visible', true)
                ->where('is_featured', true)
                ->take($limit)
                ->get()
        );
    }

    public function find(string $tenantId, int $productId): ?object
    {
        return Cache::remember(
            $this->cacheKeys->makeKey($tenantId, "product_id_{$productId}"),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->where('is_visible', true)
                ->with(['category', 'images']) // Eager load minimal relations
                ->first()
        );
    }

    public function getProductBySlug(string $tenantId, string $slug): ?Product
    {
        return Cache::remember(
            $this->cacheKeys->makeKey($tenantId, 'product_slug_'.hash('sha256', $slug)),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->where('is_visible', true)
                ->with(['category', 'images']) // Eager load minimal relations
                ->first()
        );
    }

    public function searchProducts(string $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        // Cache Key based on serialized filters to handle combinations
        $filterHash = hash('sha256', (string) json_encode($filters));
        $page = request()->get('page', 1);

        return Cache::remember(
            $this->cacheKeys->makeKey($tenantId, "products_search_{$filterHash}_page_{$page}_per_{$perPage}"),
            self::TTL_SECONDS,
            function () use ($tenantId, $filters, $perPage) {
                $query = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_visible', true);

                if (! empty($filters['search'])) {
                    $query->where('name', 'like', "%{$filters['search']}%");
                }

                if (! empty($filters['category_id'])) {
                    $query->where('category_id', $filters['category_id']);
                }

                if (! empty($filters['min_price'])) {
                    $query->where('price', '>=', $filters['min_price']);
                }

                if (! empty($filters['max_price'])) {
                    $query->where('price', '<=', $filters['max_price']);
                }

                return $query->paginate($perPage);
            }
        );
    }
}
