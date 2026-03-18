<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use App\Domain\Shared\Contracts\PaginatedResult;

/**
 * TenantProductRepository - Domain Contract for Tenant Product Queries
 *
 * ARCHITECTURAL PURPOSE:
 * Defines the READ contract for tenant product dashboard operations.
 * Implementation lives in Infrastructure layer (Eloquent, API, Cache, etc.).
 *
 * DEPENDENCY INVERSION:
 * Controllers depend on this abstraction, NOT on Eloquent Models.
 */
interface TenantProductRepository
{
    /**
     * Get paginated product list for tenant dashboard.
     *
     * @param  int  $tenantId  The tenant identifier
     * @param  string  $search  Optional search term for name/sku filtering
     * @param  int  $perPage  Number of items per page
     * @return PaginatedResult Paginated product collection
     */
    public function getDashboardList(int $tenantId, string $search = '', int $perPage = 12): PaginatedResult;

    /**
     * Count total products for a tenant.
     *
     * @param  int  $tenantId  The tenant identifier
     * @return int Total product count
     */
    public function countByTenant(int $tenantId): int;
}
