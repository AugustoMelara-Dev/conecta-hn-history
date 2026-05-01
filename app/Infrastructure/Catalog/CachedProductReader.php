<?php

declare(strict_types=1);

namespace App\Infrastructure\Catalog;

use App\Application\Shared\Contracts\TenantCacheManagerInterface;
use App\Domain\Catalog\Contracts\ProductCatalogReader;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Infrastructure\Shared\EloquentPaginatedResult;
use App\Models\Product;
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
        private readonly TenantCacheManagerInterface $cacheManager,
    ) {}

    public function getFeaturedProducts(int $tenantId, int $limit = 10): array
    {
        return Cache::remember(
            $this->cacheManager->makeKey($tenantId, "featured_limit_{$limit}"),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('is_visible', true)
                ->where('is_featured', true)
                ->take($limit)
                ->get()
                ->toArray()
        );
    }

    public function find(int $tenantId, int $productId): ?object
    {
        return Cache::remember(
            $this->cacheManager->makeKey($tenantId, "product_id_{$productId}"),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->where('is_visible', true)
                ->with(['category', 'images']) // Eager load minimal relations
                ->first()
        );
    }

    public function getProductBySlug(int $tenantId, string $slug): ?Product
    {
        return Cache::remember(
            $this->cacheManager->makeKey($tenantId, "product_slug_{$slug}"),
            self::TTL_SECONDS,
            fn () => Product::where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->where('is_visible', true)
                ->with(['category', 'images']) // Eager load minimal relations
                ->first()
        );
    }

    public function searchProducts(int $tenantId, array $filters, int $perPage = 20): PaginatedResult
    {
        $filterHash = md5(json_encode($filters));
        $page = request()->get('page', 1);

        $paginator = Cache::remember(
            $this->cacheManager->makeKey($tenantId, "search_{$filterHash}_page_{$page}_per_{$perPage}"),
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

        return new EloquentPaginatedResult($paginator);
    }
}
