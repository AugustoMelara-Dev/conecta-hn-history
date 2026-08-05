<?php

declare(strict_types=1);

namespace App\Infrastructure\Marketing;

use App\Domain\Marketing\Contracts\CouponReader;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;

/**
 * EloquentCouponReader - Eloquent Implementation
 *
 * Implements strict filtering logic for valid coupons.
 */
final class EloquentCouponReader implements CouponReader
{
    public function getValidCoupons(string $tenantId): Collection
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return Coupon::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select(['code', 'name', 'description', 'type', 'value', 'min_spend', 'expires_at'])
            ->get();
    }

    public function findByCode(string $tenantId, string $code): ?object
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return Coupon::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
    }
}
