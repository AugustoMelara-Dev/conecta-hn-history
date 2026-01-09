<?php

declare(strict_types=1);

namespace App\Domain\Products\Repositories;

/**
 * ProductReadRepositoryInterface - Read Model for Product Catalog
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CQRS LITE: Separate read path from write path                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This interface defines optimized READ operations for products.         ║
 * ║  Returns lightweight arrays/DTOs instead of full Eloquent models.       ║
 * ║  Optimized for frontend display, NOT for business logic mutations.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ProductReadRepositoryInterface
{
    /**
     * Search products with filters and pagination.
     *
     * @param int $tenantId The tenant context
     * @param array $filters Optional filters (is_active, menu_section_id, etc.)
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array{products: array, total: int, last_page: int}
     */
    public function search(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array;

    /**
     * Get a single product by ID for display.
     *
     * @param int $tenantId The tenant context
     * @param int $productId The product ID
     * @return array|null Product data or null if not found
     */
    public function findForDisplay(int $tenantId, int $productId): ?array;
}
