<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;

use Illuminate\Support\Collection;

/**
 * CouponReader - Domain Contract for Coupon Validation
 *
 * Responsibility: Obtain ACTIVE and VALID coupons only.
 */
interface CouponReader
{
    /**
     * Get valid coupons for a tenant.
     * (Active = true, Not Expired)
     *
     * @return Collection<int, object>
     */
    public function getValidCoupons(int|string $tenantId): Collection;

    /**
     * Find a specific coupon by code for a tenant.
     */
    public function findByCode(int|string $tenantId, string $code): ?object;
}
