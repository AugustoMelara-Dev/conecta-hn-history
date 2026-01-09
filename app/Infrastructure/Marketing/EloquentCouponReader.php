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
    public function getValidCoupons(int $tenantId): Collection
    {
        return Coupon::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select(['code', 'name', 'description', 'type', 'value', 'min_spend', 'expires_at'])
            ->get();
    }

    public function findByCode(int $tenantId, string $code): ?object
    {
         return Coupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
    }
}
