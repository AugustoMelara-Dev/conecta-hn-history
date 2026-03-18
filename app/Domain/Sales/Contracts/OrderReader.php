<?php

declare(strict_types=1);

namespace App\Domain\Sales\Contracts;

use App\Domain\Shared\Contracts\PaginatedResult;

/**
 * OrderReader - Read-Only Query Contract for Order Domain
 *
 * ARCHITECTURE: Port in Hexagonal Architecture.
 * This interface defines the READ operations for orders.
 * Write operations are handled by separate Action classes.
 *
 * ANTI-CORRUPTION: Controllers depend on this abstraction,
 * not on Eloquent models directly.
 */
interface OrderReader
{
    /**
     * Search orders for a specific tenant with optional filtering.
     *
     * @param  int  $tenantId  The tenant context (REQUIRED for multi-tenancy)
     * @param  string|null  $status  Optional status filter
     * @param  int  $perPage  Pagination limit
     * @return LengthAwarePaginator Paginated order results with eager-loaded relations
     */
    public function search(
        int|string $tenantId,
        ?string $status = null,
        int $perPage = 20
    ): PaginatedResult;

    /**
     * Find a single order by ID within a specific tenant.
     *
     * SECURITY: Requires explicit tenant context to prevent IDOR.
     *
     * @param  int|string  $tenantId  The tenant context (REQUIRED for multi-tenancy)
     * @param  int  $orderId  The order identifier
     * @return object|null Order entity or null
     */
    public function find(int|string $tenantId, int $orderId): ?object;

    /**
     * Get orders by status for a tenant.
     */
    public function findByStatus(int|string $tenantId, string $status, int $limit = 50): iterable;
}
