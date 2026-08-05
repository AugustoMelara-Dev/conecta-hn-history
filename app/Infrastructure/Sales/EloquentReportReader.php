<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Markets\TenantMarketResolver;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Sales\Contracts\ReportReader;
use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        private TenantMarketResolver $marketResolver,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getSalesData(string $tenantId, Carbon $startDate, Carbon $endDate): Collection
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
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
     * Revenue is always read from the server-authoritative outcome ledger. The
     * optional collection remains in the port for backwards compatibility and
     * must never be trusted as a financial source.
     */
    public function getSalesSummary(
        string $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        ?Collection $preloadedOrders = null
    ): array {
        // INFRA_TENANT_FILTER: exact tenant identity is supplied by the trusted report boundary.
        $tenantAttributes = DB::table('tenants')->where('id', $tenantId)->first();
        if ($tenantAttributes === null) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
        }
        $tenant = (new Tenant)->newFromBuilder((array) $tenantAttributes);
        $currency = $this->marketResolver->resolve($tenant)->currencyCode;
        $outcomes = DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->where('aggregate_type', 'order')
            ->whereIn('outcome', [
                CommercialOutcome::OrderPaid->value,
                CommercialOutcome::OrderRefunded->value,
            ])
            ->where('currency', $currency)
            ->whereBetween('source_occurred_at', [$startDate, $endDate]);

        $totalRevenueMinor = (int) (clone $outcomes)->sum('amount_minor');
        $orderCount = (clone $outcomes)
            ->where('outcome', CommercialOutcome::OrderPaid->value)
            ->distinct()
            ->count('aggregate_id');
        $totalRevenue = (float) ($totalRevenueMinor / 100);
        $averageOrder = $orderCount > 0 ? $totalRevenue / $orderCount : 0.0;

        return [
            'currency' => $currency,
            'total_revenue' => $totalRevenue,
            'order_count' => $orderCount,
            'average_order' => round($averageOrder, 2),
        ];
    }
}
