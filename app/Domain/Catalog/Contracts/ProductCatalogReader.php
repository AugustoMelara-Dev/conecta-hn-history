<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Contracts;

use App\Domain\Shared\Contracts\PaginatedResult;

interface ProductCatalogReader
{
    /**
     * Get featured products for a tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeaturedProducts(int $tenantId, int $limit = 10): array;

    /**
     * Get a single product by slug and tenant.
     */
    public function getProductBySlug(int $tenantId, string $slug): ?object;

    /**
     * Search products with filters.
     *
     * @param  array  $filters  ['category_id', 'search', 'min_price', 'max_price', 'sort']
     */
    public function searchProducts(int $tenantId, array $filters, int $perPage = 20): PaginatedResult;
}
