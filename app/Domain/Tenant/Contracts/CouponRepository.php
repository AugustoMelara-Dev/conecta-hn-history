<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * CouponRepository - Domain Contract for Marketing Dashboard
 *
 * ARCHITECTURE:
 * Abstraction layer to decouple Controller from Eloquent.
 */
interface CouponRepository
{
    /**
     * Get paginated list of coupons for dashboard.
     *
     * @param int $tenantId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getDashboardList(int $tenantId, int $perPage = 10): LengthAwarePaginator;
}
