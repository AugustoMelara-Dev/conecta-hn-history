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
    public function getKpiSummary(int|string $tenantId): array;

    /**
     * Get mode-specific conversion KPIs for the tenant dashboard.
     */
    public function getCommercialModeKpis(int|string $tenantId): array;

    /**
     * Get revenue trend data.
     */
    public function getRevenueTrend(int|string $tenantId, string $period = 'month'): array;

    /**
     * Get onboarding status.
     */
    public function getOnboardingStatus(int|string $tenantId): array;

    /**
     * Get latest orders.
     */
    public function getLatestOrders(int|string $tenantId, int $limit = 5): array;
}
