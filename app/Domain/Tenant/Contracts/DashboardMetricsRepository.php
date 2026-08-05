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
    public function getKpiSummary(string $tenantId): array;

    /**
     * Get mode-specific conversion KPIs for the tenant dashboard.
     */
    public function getCommercialModeKpis(string $tenantId): array;

    /**
     * Get revenue trend data.
     */
    public function getRevenueTrend(string $tenantId, string $period = 'month'): array;

    /**
     * Get onboarding status.
     */
    public function getOnboardingStatus(string $tenantId): array;

    /**
     * Get latest orders.
     */
    public function getLatestOrders(string $tenantId, int $limit = 5): array;
}
