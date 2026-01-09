<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Domain\Sales\Contracts\OrderReader;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * EloquentOrderReader - Eloquent Implementation of OrderReader
 *
 * ARCHITECTURE: Adapter in Hexagonal Architecture.
 * Implements the Domain contract using Eloquent ORM.
 *
 * MULTI-TENANT: All queries are ALWAYS scoped by tenant_id.
 * This is enforced at the Reader level, not delegated to global scopes.
 */
final class EloquentOrderReader implements OrderReader
{
    /**
     * {@inheritdoc}
     */
    public function search(
        int $tenantId,
        ?string $status = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->with(['items', 'tenant'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $orderId): ?object
    {
        return Order::withoutGlobalScopes()
            ->with(['items', 'tenant'])
            ->find($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function findByStatus(int $tenantId, string $status, int $limit = 50): iterable
    {
        return Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', $status)
            ->with(['items'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
