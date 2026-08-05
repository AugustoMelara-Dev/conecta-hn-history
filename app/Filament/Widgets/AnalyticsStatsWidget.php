<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Interaction;
use App\Models\TenantVisit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * AnalyticsStatsWidget - Executive KPI Dashboard
 *
 * Displays key metrics for the tenant owner:
 * - Total visits this month
 * - WhatsApp conversions
 * - Conversion rate
 */
class AnalyticsStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Auth::user()?->tenant;

        if (! $tenant) {
            return [
                Stat::make('Visitas', '-')
                    ->description('No hay negocio vinculado')
                    ->color('gray'),
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // CURRENT MONTH METRICS
        // ═══════════════════════════════════════════════════════════════
        $visitsThisMonth = TenantVisit::forTenant($tenant->id)
            ->thisMonth()
            ->count();

        $whatsappIntentsThisMonth = Interaction::forTenant($tenant->id)
            ->ofType('whatsapp_click')
            ->thisMonth()
            ->count();

        // ═══════════════════════════════════════════════════════════════
        // PREVIOUS MONTH METRICS (for trend comparison)
        // ═══════════════════════════════════════════════════════════════
        $visitsLastMonth = TenantVisit::forTenant($tenant->id)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $whatsappIntentsLastMonth = Interaction::forTenant($tenant->id)
            ->ofType('whatsapp_click')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        // ═══════════════════════════════════════════════════════════════
        // CALCULATE TRENDS
        // ═══════════════════════════════════════════════════════════════
        $visitsTrend = $this->calculateTrend($visitsThisMonth, $visitsLastMonth);
        $whatsappIntentsTrend = $this->calculateTrend($whatsappIntentsThisMonth, $whatsappIntentsLastMonth);

        // Contact-intent rate. This is explicitly not a verified sale.
        $contactRate = $visitsThisMonth > 0
            ? round(($whatsappIntentsThisMonth / $visitsThisMonth) * 100, 1)
            : 0;

        return [
            Stat::make('Visitas este mes', number_format($visitsThisMonth))
                ->description($visitsTrend['description'])
                ->descriptionIcon($visitsTrend['icon'])
                ->color($visitsTrend['color'])
                ->chart($this->getSparklineData($tenant->id, 'visits')),

            Stat::make('Intenciones por WhatsApp', number_format($whatsappIntentsThisMonth))
                ->description($whatsappIntentsTrend['description'].' · no equivale a venta')
                ->descriptionIcon($whatsappIntentsTrend['icon'])
                ->color($whatsappIntentsTrend['color'])
                ->chart($this->getSparklineData($tenant->id, 'conversions')),

            Stat::make('Tasa de contacto', "{$contactRate}%")
                ->description('Visitas → intención de WhatsApp')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($contactRate >= 5 ? 'success' : ($contactRate >= 2 ? 'warning' : 'gray')),
        ];
    }

    /**
     * Calculate trend compared to previous period.
     */
    protected function calculateTrend(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'description' => 'Sin datos previos',
                'icon' => 'heroicon-o-minus',
                'color' => 'gray',
            ];
        }

        $change = (($current - $previous) / $previous) * 100;

        if ($change > 0) {
            return [
                'description' => '+'.round($change).'% vs mes anterior',
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'success',
            ];
        } elseif ($change < 0) {
            return [
                'description' => round($change).'% vs mes anterior',
                'icon' => 'heroicon-o-arrow-trending-down',
                'color' => 'danger',
            ];
        }

        return [
            'description' => 'Sin cambios',
            'icon' => 'heroicon-o-minus',
            'color' => 'gray',
        ];
    }

    /**
     * Get sparkline data for last 7 days.
     */
    protected function getSparklineData(string $tenantId, string $metric): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        if ($metric === 'visits') {
            $data = TenantVisit::forTenant($tenantId)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->pluck('count', 'date');
        } else {
            $data = Interaction::forTenant($tenantId)
                ->ofType('whatsapp_click')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->pluck('count', 'date');
        }

        return $days->map(fn ($date) => $data[$date] ?? 0)->toArray();
    }
}
