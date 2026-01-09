<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\OrderRepository;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * EloquentOrderRepository - Infrastructure Implementation
 *
 * SINGLE RESPONSIBILITY:
 * This class is the ONLY place where Eloquent Order queries exist
 * for the Tenant dashboard context.
 *
 * OPTIMIZATION:
 * Uses eager loading to prevent N+1 query problems.
 */
final class EloquentOrderRepository implements OrderRepository
{
    /**
     * @inheritDoc
     */
    public function getDashboardKanban(int $tenantId, array $filters = []): Collection
    {
        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->with(['items.product:id,name,price,image_path'])
            ->orderByDesc('created_at');

        // Apply date filter (default: today)
        if (isset($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        } else {
            $query->whereDate('created_at', today());
        }

        // Apply status filter if specified
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->get()->groupBy('status');
    }

    /**
     * @inheritDoc
     */
    public function getDashboardList(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->with(['items.product:id,name,price,image_path'])
            ->orderByDesc('created_at');

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @inheritDoc
     */
    public function findForDetails(int $tenantId, int $orderId): mixed
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->with(['items.product:id,name,price,image_path'])
            ->find($orderId);
    }

    /**
     * @inheritDoc
     */
    public function updateStatus(int $tenantId, int $orderId, string $status): bool
    {
        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $orderId)
            ->update(['status' => $status]) > 0;
    }
}
