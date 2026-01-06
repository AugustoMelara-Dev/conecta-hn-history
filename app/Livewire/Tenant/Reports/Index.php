<?php

declare(strict_types=1);

namespace App\Livewire\Tenant\Reports;

use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reports Index - Analytics Dashboard
 *
 * Features:
 * - KPI cards with cached aggregations
 * - Chart.js integration with data filling
 * - Tiered access (FREE: today only, PRO: full history)
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Tenant $tenant;

    // Date range filter (PRO: selectable, FREE: forced to 'today')
    public string $dateRange = '30_days';

    // ═══════════════════════════════════════════════════════════════════════
    // LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            abort(403, 'No tienes un negocio asociado.');
        }

        $this->tenant = $user->tenant;

        // Security: Force FREE users to 'today' only
        if (! $this->tenant->is_pro) {
            $this->dateRange = 'today';
        }
    }

    /**
     * Handle date range change with FREE restriction
     */
    public function updatedDateRange(): void
    {
        if (! $this->tenant->is_pro) {
            $this->dateRange = 'today';
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    /**
     * Get date range bounds
     */
    #[Computed]
    public function dateRangeBounds(): array
    {
        return match ($this->dateRange) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            '7_days' => [today()->subDays(7)->startOfDay(), today()->endOfDay()],
            '30_days' => [today()->subDays(30)->startOfDay(), today()->endOfDay()],
            default => [today()->startOfDay(), today()->endOfDay()],
        };
    }

    /**
     * KPI Statistics with caching
     */
    #[Computed]
    public function kpiStats(): array
    {
        $tenantId = $this->tenant->id;
        $range = $this->dateRange;
        [$startDate, $endDate] = $this->dateRangeBounds;

        // Get last order ID for cache invalidation hint
        $lastOrderId = Order::where('tenant_id', $tenantId)->max('id') ?? 0;

        $cacheKey = "tenant.{$tenantId}.stats.{$range}.{$lastOrderId}";

        return Cache::remember($cacheKey, 600, function () use ($tenantId, $startDate, $endDate) {
            $query = Order::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['completed', 'delivered']);

            $totalRevenue = (clone $query)->sum('total');
            $totalOrders = Order::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotIn('status', ['cancelled'])
                ->count();

            $avgTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

            return [
                'revenue' => (float) $totalRevenue,
                'orders' => (int) $totalOrders,
                'avg_ticket' => (float) $avgTicket,
            ];
        });
    }

    /**
     * Chart data with data filling algorithm
     */
    #[Computed]
    public function chartData(): array
    {
        // FREE users don't get chart data
        if (! $this->isPro) {
            return [
                'labels' => [],
                'revenue' => [],
                'orders' => [],
            ];
        }

        $tenantId = $this->tenant->id;
        [$startDate, $endDate] = $this->dateRangeBounds;

        // Aggregated query by date
        $rawData = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Data filling algorithm - ensure no gaps
        $labels = [];
        $revenue = [];
        $orders = [];

        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $displayLabel = $current->format('d M');

            $labels[] = $displayLabel;
            $revenue[] = isset($rawData[$dateKey]) ? (float) $rawData[$dateKey]->revenue : 0;
            $orders[] = isset($rawData[$dateKey]) ? (int) $rawData[$dateKey]->orders : 0;

            $current->addDay();
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    /**
     * Date range options
     */
    #[Computed]
    public function rangeOptions(): array
    {
        return [
            'today' => 'Hoy',
            '7_days' => '7 Días',
            '30_days' => '30 Días',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.reports.index')
            ->title('Reportes | '.$this->tenant->name);
    }
}
