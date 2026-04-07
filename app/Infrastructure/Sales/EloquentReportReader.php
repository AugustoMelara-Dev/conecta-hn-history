<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Domain\Sales\Contracts\ReportReader;
use App\Models\Order;
use Carbon\Carbon;

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
    public function getSalesData(int|string $tenantId, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $carbonStart = Carbon::instance($startDate);
        $carbonEnd = Carbon::instance($endDate);

        return Order::withoutGlobalScopes()
            ->where('tenant_id', (string) $tenantId)
            ->whereBetween('created_at', [$carbonStart, $carbonEnd])
            ->whereIn('status', ['completed', 'paid', 'delivered'])
            ->with('items')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * {@inheritdoc}
     *
     * OPTIMIZATION: If $preloadedOrders is provided, uses in-memory calculation.
     * Otherwise, executes a fresh database query (fallback for standalone usage).
     */
    public function getSalesSummary(
        int|string $tenantId,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?array $preloadedOrders = null
    ): array {
        $orders = $preloadedOrders !== null
            ? collect($preloadedOrders)
            : collect($this->getSalesData($tenantId, $startDate, $endDate));

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
