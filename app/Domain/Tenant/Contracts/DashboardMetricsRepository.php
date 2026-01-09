<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Contracts;

/**
 * DashboardMetricsRepository - Domain Contract
 */
interface DashboardMetricsRepository
{
    /**
     * Get KPI summary stats.
     */
    public function getKpiSummary(int $tenantId): array;

    /**
     * Get revenue trend data.
     */
    public function getRevenueTrend(int $tenantId, string $period = 'month'): array;

    /**
     * Get onboarding status.
     */
    public function getOnboardingStatus(int $tenantId): array;

    /**
     * Get latest orders.
     */
    public function getLatestOrders(int $tenantId, int $limit = 5): array;
}
