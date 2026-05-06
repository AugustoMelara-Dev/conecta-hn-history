<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Domain\Sales\Contracts\ReportReader;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * EloquentReportReader - Eloquent Implementation of ReportReader
 *
 * ADAPTER: Implements the Domain contract using Eloquent ORM.
 *
 * MULTI-TENANT: All queries explicitly scoped by tenant_id.
 * PERFORMANCE: Uses eager loading (with) to prevent N+1.
 * OPTIMIZATION: getSalesSummary reuses preloaded data to avoid double-querying.
 */
final class EloquentReportReader implements ReportReader
{
    /**
     * {@inheritdoc}
     */
    public function getSalesData(int $tenantId, Carbon $startDate, Carbon $endDate): Collection
    {
        // DEFENSIVE: Explicit cast in case of ValueObject leakage
        $tenantId = (int) $tenantId;

        return Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'paid', 'delivered'])
            ->with('items')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * {@inheritdoc}
     *
     * OPTIMIZATION: If $preloadedOrders is provided, uses in-memory calculation.
     * Otherwise, executes a fresh database query (fallback for standalone usage).
     */
    public function getSalesSummary(
        int $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        ?Collection $preloadedOrders = null
    ): array {
        // Use preloaded data if available (O(1) SQL queries)
        // Otherwise, fetch fresh data (1 SQL query - fallback)
        $orders = $preloadedOrders ?? $this->getSalesData($tenantId, $startDate, $endDate);

        $totalRevenue = (float) $orders->sum('total');
        $orderCount = $orders->count();
        $averageOrder = $orderCount > 0 ? $totalRevenue / $orderCount : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'order_count' => $orderCount,
            'average_order' => round($averageOrder, 2),
        ];
    }
}
