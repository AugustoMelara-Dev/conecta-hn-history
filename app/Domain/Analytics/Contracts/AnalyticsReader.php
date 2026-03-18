<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use DateTimeImmutable;

/**
 * AnalyticsReader - Domain Contract for Analytics Data
 *
 * PORT: Defines how the Application Layer retrieves analytics metrics.
 * ADAPTER: Implemented by Infrastructure layer (EloquentAnalyticsReader).
 */
interface AnalyticsReader
{
    /**
     * Get analytics data for a tenant within a date range.
     *
     * @param  int  $tenantId  Tenant ID
     * @param  DateTimeImmutable  $startDate  Range start
     * @param  DateTimeImmutable  $endDate  Range end
     * @return array<int, array{date: string, visits: int, total: int}> Daily analytics records
     */
    public function getAnalyticsData(int $tenantId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array;

    /**
     * Get filled analytics with zero-value gap filling.
     *
     * @param  int  $tenantId  Tenant ID
     * @param  int  $days  Number of days to retrieve
     * @return array{
     *     data: array<int, array{date: string, visits: int, total: int}>,
     *     summary: array{
     *         total_unique_visits: int,
     *         average_daily_visits: float,
     *         peak_day: array{date: string, visits: int}|null
     *     }
     * }
     */
    public function getFilledAnalytics(int $tenantId, int $days): array;
}
