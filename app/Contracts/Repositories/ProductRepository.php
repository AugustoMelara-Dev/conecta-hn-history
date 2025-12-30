<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

/**
 * ProductRepository Contract - Domain-Specific Product Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Specialized Repository for Products           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  Extends the generic RepositoryContract with Product-specific queries.  ║
 * ║  All methods are AUTOMATICALLY scoped to the CurrentTenant.             ║
 * ║                                                                          ║
 * ║  The consuming code (ProductController, CatalogService) only knows      ║
 * ║  about this interface - they have no knowledge of Eloquent, caching,    ║
 * ║  or Elasticsearch underneath.                                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ProductRepository extends RepositoryContract
{
    /**
     * Get featured products for the tenant's homepage.
     *
     * @param int $limit
     * @return Collection Products with is_featured = true
     */
    public function getFeatured(int $limit = 8): Collection;

    /**
     * Get products by category slug.
     *
     * @param string $categorySlug
     * @param int $perPage
     * @return mixed Paginated products
     */
    public function getByCategory(string $categorySlug, int $perPage = 16): mixed;

    /**
     * Search products by keyword.
     *
     * @param string $query
     * @param array $filters ['min_price' => 100, 'category_id' => 5]
     * @param int $perPage
     * @return mixed Paginated search results
     */
    public function search(string $query, array $filters = [], int $perPage = 16): mixed;

    /**
     * Get products with low stock.
     *
     * @param int $threshold Minimum stock level
     * @return Collection
     */
    public function getLowStock(int $threshold = 5): Collection;

    /**
     * Get recently added products.
     *
     * @param int $days
     * @param int $limit
     * @return Collection
     */
    public function getRecentlyAdded(int $days = 7, int $limit = 12): Collection;

    /**
     * Find product by slug (for SEO-friendly URLs).
     *
     * @param string $slug
     * @return mixed Product or null
     */
    public function findBySlug(string $slug): mixed;

    /**
     * Increment product view count (for analytics).
     *
     * @param int $productId
     */
    public function incrementViews(int $productId): void;

    /**
     * Update stock quantity.
     *
     * @param int $productId
     * @param int $quantity New quantity (absolute) or delta
     * @param bool $isDelta If true, adds to current stock; if false, sets stock
     */
    public function updateStock(int $productId, int $quantity, bool $isDelta = false): void;

    /**
     * Get products that are on sale.
     *
     * @param int $limit
     * @return Collection Products with active discounts
     */
    public function getOnSale(int $limit = 12): Collection;

    /**
     * Get related products based on category and tags.
     *
     * @param int $productId
     * @param int $limit
     * @return Collection
     */
    public function getRelated(int $productId, int $limit = 4): Collection;
}
