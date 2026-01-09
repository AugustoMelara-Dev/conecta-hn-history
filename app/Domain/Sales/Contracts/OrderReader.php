<?php

declare(strict_types=1);

namespace App\Domain\Sales\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * @param int $tenantId The tenant context (REQUIRED for multi-tenancy)
     * @param string|null $status Optional status filter
     * @param int $perPage Pagination limit
     * @return LengthAwarePaginator Paginated order results with eager-loaded relations
     */
    public function search(
        int $tenantId,
        ?string $status = null,
        int $perPage = 20
    ): LengthAwarePaginator;

    /**
     * Find a single order by ID.
     *
     * @param int $orderId
     * @return object|null Order entity or null
     */
    public function find(int $orderId): ?object;

    /**
     * Get orders by status for a tenant.
     *
     * @param int $tenantId
     * @param string $status
     * @param int $limit
     * @return iterable
     */
    public function findByStatus(int $tenantId, string $status, int $limit = 50): iterable;
}
