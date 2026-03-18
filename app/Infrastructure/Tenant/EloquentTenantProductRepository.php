<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Shared\Contracts\PaginatedResult;
use App\Domain\Tenant\Contracts\TenantProductRepository;
use App\Infrastructure\Shared\EloquentPaginatedResult;
use App\Models\Product; // Allowed in Infra

/**
 * EloquentTenantProductRepository - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Handle Product queries with encapsulated logic (sorting, filtering).
 */
final class EloquentTenantProductRepository implements TenantProductRepository
{
    /**
     * {@inheritDoc}
     */
    public function getDashboardList(int $tenantId, string $search = '', int $perPage = 12): PaginatedResult
    {
        return new EloquentPaginatedResult(
            Product::query()
                ->where('tenant_id', $tenantId)
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->with(['category', 'images'])
                ->orderBy('name')
                ->paginate($perPage)
        );
    }

    // Include other methods from interface if they exist, assuming interface was defined previously
    // or reusing existing methods. Based on context, I am "Updating Infrastructure".
    // I will stick to what is needed for the dashboard list fix.

    public function countByTenant(int $tenantId): int
    {
        return Product::where('tenant_id', $tenantId)->count();
    }
}
