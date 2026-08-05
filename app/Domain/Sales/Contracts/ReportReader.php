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
 * Revenue summaries are sourced only from verified server outcomes. The
 * optional order collection is retained solely for backwards compatibility.
 */
interface ReportReader
{
    /**
     * Get sales data for a tenant within a date range.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  Carbon  $startDate  Range start (inclusive)
     * @param  Carbon  $endDate  Range end (inclusive)
     * @return Collection Orders with items eager loaded
     */
    public function getSalesData(string $tenantId, Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get sales summary statistics.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  Carbon  $startDate  Range start
     * @param  Carbon  $endDate  Range end
     * @param  Collection|null  $preloadedOrders  Deprecated compatibility argument; never a financial source
     * @return array{total_revenue: float, order_count: int, average_order: float}
     */
    public function getSalesSummary(
        string $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        ?Collection $preloadedOrders = null
    ): array;
}
