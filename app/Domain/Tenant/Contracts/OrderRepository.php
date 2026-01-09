<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;


use Illuminate\Support\Collection;

/**
 * OrderRepository - Domain Contract for Tenant Order Queries
 *
 * ARCHITECTURAL PURPOSE:
 * Defines the READ contract for tenant order dashboard operations.
 * Implementation lives in Infrastructure layer (Eloquent, API, Cache, etc.).
 *
 * DEPENDENCY INVERSION:
 * Controllers depend on this abstraction, NOT on Eloquent Models.
 */
interface OrderRepository
{
    /**
     * Get orders for dashboard Kanban view, grouped by status.
     *
     * @param int $tenantId The tenant identifier
     * @param array<string, mixed> $filters Optional filters (date, status, etc.)
     * @return Collection<string, Collection> Orders grouped by status
     */
    public function getDashboardKanban(int $tenantId, array $filters = []): Collection;

    /**
     * Get paginated order list.
     *
     * @param int $tenantId The tenant identifier
     * @param array<string, mixed> $filters Optional filters
     * @param int $perPage Items per page
     * @return mixed Paginator Contract or Array (Infrastructure dependent)
     */
    public function getDashboardList(int $tenantId, array $filters = [], int $perPage = 15): mixed;

    /**
     * Find a specific order with details for display.
     *
     * @param int $tenantId The tenant identifier (for scope/security)
     * @param int $orderId The order identifier
     * @return mixed Order with eager-loaded relations or null
     */
    public function findForDetails(int $tenantId, int $orderId): mixed;

    /**
     * Update order status.
     *
     * @param int $tenantId The tenant identifier
     * @param int $orderId The order identifier
     * @param string $status New status value
     * @return bool Success flag
     */
    public function updateStatus(int $tenantId, int $orderId, string $status): bool;
}
