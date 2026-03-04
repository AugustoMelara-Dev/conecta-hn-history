<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Contracts;


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
    public function getValidCoupons(int $tenantId): array;

    /**
     * Find a specific coupon by code for a tenant.
     */
    public function findByCode(int $tenantId, string $code): ?object;
}
