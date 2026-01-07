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
     * @return Collection<int, \App\Models\Product>
     */
    public function getFeaturedProducts(int $tenantId, int $limit = 10): Collection;

    /**
     * Get a single product by slug and tenant.
     */
    public function getProductBySlug(int $tenantId, string $slug): ?\App\Models\Product;

    /**
     * Search products with filters.
     *
     * @param array $filters ['category_id', 'search', 'min_price', 'max_price', 'sort']
     */
    public function searchProducts(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator;
}
