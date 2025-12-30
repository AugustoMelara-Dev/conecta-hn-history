<?php

namespace App\Livewire\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tenant Dashboard - Dual Interface (Business Intelligence vs. Conversion)
 *
 * Strategies:
 * - PRO: ROI Validation, Interactive Charts, Retention Metrics.
 * - FREE: Scarcity, Loss Aversion, Friction.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public const LIMIT_FREE = 10;

    public const LIMIT_PRO = 100;

    public const DANGER_THRESHOLD = 80;

    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        // SECURITY: Verify ownership
        if (Auth::id() !== $tenant->user_id) {
            abort(403, 'No tienes permiso para acceder a este panel.');
        }

        $this->tenant = $tenant;
    }

    #[Computed]
    public function stats(): array
    {
        $productCount = $this->tenant->products()->count();
        $limit = $this->tenant->is_pro ? self::LIMIT_PRO : self::LIMIT_FREE;
        $percentage = $limit > 0 ? round(($productCount / $limit) * 100) : 0;

        return [
            // Resource Limits
            'product_count' => $productCount,
            'product_limit' => $limit,
            'product_percentage' => min($percentage, 100),
            'is_at_limit' => $productCount >= $limit,
            'is_danger_zone' => $percentage >= self::DANGER_THRESHOLD,
            'products_remaining' => max(0, $limit - $productCount),

            // Traffic Metrics
            'total_visits' => $this->tenant->tenant_visits_count ?? 0,
            'visits_today' => $this->getTodayVisits(),
            'visits_this_week' => $this->getWeeklyVisits(),

            // Engagement Metrics
            'whatsapp_clicks' => $this->tenant->whatsapp_clicks_count ?? 0,
            'favorites_count' => $this->tenant->favorites()->count(),

            // PRO KPI (30 Days ROI)
            'monthly_conversions' => $this->tenant->interactions()
                ->where('type', 'whatsapp')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    #[Computed]
    public function isFree(): bool
    {
        return ! $this->tenant->is_pro;
    }

    /**
     * Data for Chart.js (Cached for 15 mins)
     */
    #[Computed]
    public function chartData(): array
    {
        if ($this->isFree) {
            return [];
        }

        // Cache Key única por tenant
        $cacheKey = "tenant.{$this->tenant->id}.dashboard.chart";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            return $this->buildChartData();
        });
    }

    /**
     * Heavy lifting: Data Aggregation & Filling
     */
    protected function buildChartData(): array
    {
        // 1. Raw Queries
        $visitsRaw = $this->tenant->tenantVisits()
            ->selectRaw('DATE(visited_at) as date, count(*) as count')
            ->where('visited_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $clicksRaw = $this->tenant->interactions()
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->where('type', 'whatsapp')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // 2. Data Filling (0 for missing days)
        $labels = [];
        $visits = [];
        $conversions = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');

            $labels[] = $date->format('d M'); // "12 May"
            $visits[] = $visitsRaw[$key] ?? 0;
            $conversions[] = $clicksRaw[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'visits' => $visits,
            'conversions' => $conversions,
        ];
    }

    /**
     * Subscription Mock (Pre-Cashier)
     */
    #[Computed]
    public function subscription(): array
    {
        return [
            'status' => 'Activa',
            'plan_name' => 'Plan PRO Mensual',
            'next_billing_date' => now()->addDays(12)->format('d M, Y'),
            'amount' => 'L. 1,500.00',
            'invoice_url' => '#',
        ];
    }

    protected function getTodayVisits(): int
    {
        return $this->tenant->tenantVisits()->whereDate('visited_at', today())->count();
    }

    protected function getWeeklyVisits(): int
    {
        return $this->tenant->tenantVisits()
            ->whereBetween('visited_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
    }

    public function render()
    {
        return view('livewire.tenant.dashboard')
            ->title($this->tenant->name.' - Dashboard | Conecta HN');
    }
}
