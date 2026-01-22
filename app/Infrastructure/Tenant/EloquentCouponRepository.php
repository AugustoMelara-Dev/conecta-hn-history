<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\CouponRepository;
use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * EloquentCouponRepository - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Handle Eloquent queries for Coupons.
 */
final class EloquentCouponRepository implements CouponRepository
{
    /**
     * {@inheritDoc}
     */
    public function getDashboardList(int $tenantId, int $perPage = 10): LengthAwarePaginator
    {
        return Coupon::query()
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'code',
                'discount_value',
                'type',
                'expires_at',
                'usage_count',
                'is_active',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
