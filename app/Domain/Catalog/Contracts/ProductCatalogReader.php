<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductCatalogReader
{
    /**
     * Get featured products for a tenant.
     *
     * @return Collection<int, object>
     */
    public function getFeaturedProducts(string $tenantId, int $limit = 10): Collection;

    /**
     * Get a single product by slug and tenant.
     */
    public function getProductBySlug(string $tenantId, string $slug): ?object;

    /**
     * Search products with filters.
     *
     * @param  array  $filters  ['category_id', 'search', 'min_price', 'max_price', 'sort']
     */
    public function searchProducts(string $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;
}
