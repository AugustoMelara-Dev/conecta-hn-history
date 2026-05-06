<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

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
     * @param  Carbon  $startDate  Range start
     * @param  Carbon  $endDate  Range end
     * @return Collection Daily analytics records
     */
    public function getAnalyticsData(int $tenantId, Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Get filled analytics with zero-value gap filling.
     *
     * @param  int  $tenantId  Tenant ID
     * @param  int  $days  Number of days to retrieve
     * @return array{data: array, summary: array}
     */
    public function getFilledAnalytics(int $tenantId, int $days): array;
}
