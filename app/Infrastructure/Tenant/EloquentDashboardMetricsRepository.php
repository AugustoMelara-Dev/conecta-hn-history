<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\DashboardMetricsRepository;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * EloquentDashboardMetricsRepository - Logic Concentration
 */
final class EloquentDashboardMetricsRepository implements DashboardMetricsRepository
{
    public function getKpiSummary(int $tenantId): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayRevenue = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $today)
            ->whereIn('status', ['completed', 'paid'])
            ->sum('total');

        $yesterdayRevenue = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $yesterday)
            ->whereIn('status', ['completed', 'paid'])
            ->sum('total');

        $revenueTrend = $yesterdayRevenue > 0
            ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100
            : 0;

        return [
            'today_revenue' => (float) $todayRevenue,
            'today_orders' => Order::where('tenant_id', $tenantId)->whereDate('created_at', $today)->count(),
            'pending_orders' => Order::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'revenue_trend' => round($revenueTrend, 1),
        ];
    }

    public function getRevenueTrend(int $tenantId, string $period = 'month'): array
    {
        // Placeholder for chart data structure
        // Logic would go here (GroupBy date, etc)
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }

    public function getOnboardingStatus(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return ['progress' => 0, 'steps' => []];
        }

        // Check if steps definitions should be in Config/Domain vs hidden here.
        // For now, Repository is a better place than Controller.
        $steps = [
            ['id' => 'logo', 'title' => 'Subir logo', 'completed' => (bool) $tenant->logo_path, 'href' => route('tenant.settings.index')],
            ['id' => 'product', 'title' => 'Agregar producto', 'completed' => $tenant->products()->exists(), 'href' => route('tenant.products.create')],
            ['id' => 'schedule', 'title' => 'Definir horarios', 'completed' => ! empty($tenant->schedule), 'href' => route('tenant.settings.index')],
            ['id' => 'phone', 'title' => 'Agregar teléfono', 'completed' => (bool) $tenant->phone, 'href' => route('tenant.settings.index')],
        ];

        $completed = collect($steps)->where('completed', true)->count();
        $total = count($steps);
        $progress = $total > 0 ? round(($completed / $total) * 100) : 100;

        return ['progress' => $progress, 'steps' => $steps];
    }

    public function getLatestOrders(int $tenantId, int $limit = 5): array
    {
        return Order::where('tenant_id', $tenantId)
            ->with(['items']) // Eloquent eager load
            ->latest()
            ->limit($limit)
            ->get() // Collection
            ->map(fn ($order) => [
                'id' => $order->id,
                'customer_name' => $order->customer_name,
                'total' => (float) $order->total,
                'status' => $order->status,
                'items_count' => $order->items->count(),
                'created_at' => $order->created_at->toIso8601String(),
            ])
            ->toArray();
    }
}
