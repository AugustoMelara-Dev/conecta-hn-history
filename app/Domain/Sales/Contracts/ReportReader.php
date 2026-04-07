<?php

declare(strict_types=1);

namespace App\Domain\Sales\Contracts;

use DateTimeImmutable;

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
     * @param  int|string  $tenantId  Tenant ID
     * @param  DateTimeImmutable  $startDate  Range start (inclusive)
     * @param  DateTimeImmutable  $endDate  Range end (inclusive)
     * @return array<int, mixed> Orders with items eager loaded
     */
    public function getSalesData(int|string $tenantId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array;

    /**
     * Get sales summary statistics.
     *
     * OPTIMIZATION: Pass $preloadedOrders from getSalesData() to avoid re-querying.
     *
     * @param  int|string  $tenantId  Tenant ID
     * @param  DateTimeImmutable  $startDate  Range start
     * @param  DateTimeImmutable  $endDate  Range end
     * @param  array<int, mixed>|null  $preloadedOrders  Optional - reuse data from getSalesData()
     * @return array{total_revenue: float, order_count: int, average_order: float}
     */
    public function getSalesSummary(
        int|string $tenantId,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        ?array $preloadedOrders = null
    ): array;
}
