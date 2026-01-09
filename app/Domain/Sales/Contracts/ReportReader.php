<?php

declare(strict_types=1);

namespace App\Domain\Sales\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ReportReader - Domain Contract for Sales Report Data
 *
 * PORT: Defines how the Application Layer retrieves sales data.
 * ADAPTER: Implemented by Infrastructure layer (EloquentReportReader).
 *
 * OPTIMIZATION NOTE:
 * getSalesSummary accepts optional $preloadedOrders to avoid double-querying.
 * Callers should pass the result of getSalesData() when both are needed.
 */
interface ReportReader
{
    /**
     * Get sales data for a tenant within a date range.
     *
     * @param int $tenantId Tenant ID
     * @param Carbon $startDate Range start (inclusive)
     * @param Carbon $endDate Range end (inclusive)
     * @return Collection Orders with items eager loaded
     */
    public function getSalesData(int $tenantId, Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get sales summary statistics.
     *
     * OPTIMIZATION: Pass $preloadedOrders from getSalesData() to avoid re-querying.
     *
     * @param int $tenantId Tenant ID
     * @param Carbon $startDate Range start
     * @param Carbon $endDate Range end
     * @param Collection|null $preloadedOrders Optional - reuse data from getSalesData()
     * @return array{total_revenue: float, order_count: int, average_order: float}
     */
    public function getSalesSummary(
        int $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        ?Collection $preloadedOrders = null
    ): array;
}
