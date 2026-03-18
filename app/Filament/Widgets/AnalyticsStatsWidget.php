<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Interaction;
use App\Models\Tenant;
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
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [
                Stat::make('Visitas', '-')
                    ->description('No hay negocio vinculado')
                    ->color('gray'),
            ];
        }

        $tenantId = (string) $tenant->getKey();

        // ═══════════════════════════════════════════════════════════════
        // CURRENT MONTH METRICS
        // ═══════════════════════════════════════════════════════════════
        $visitsThisMonth = TenantVisit::query()
            ->where('tenant_id', $tenantId)
            ->thisMonth()
            ->count();

        $conversionsThisMonth = Interaction::query()
            ->where('tenant_id', $tenantId)
            ->ofType(Interaction::TYPE_WHATSAPP_CLICK)
            ->thisMonth()
            ->count();

        // ═══════════════════════════════════════════════════════════════
        // PREVIOUS MONTH METRICS (for trend comparison)
        // ═══════════════════════════════════════════════════════════════
        $visitsLastMonth = TenantVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $conversionsLastMonth = Interaction::query()
            ->where('tenant_id', $tenantId)
            ->ofType(Interaction::TYPE_WHATSAPP_CLICK)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        // ═══════════════════════════════════════════════════════════════
        // CALCULATE TRENDS
        // ═══════════════════════════════════════════════════════════════
        $visitsTrend = $this->calculateTrend($visitsThisMonth, $visitsLastMonth);
        $conversionsTrend = $this->calculateTrend($conversionsThisMonth, $conversionsLastMonth);

        // Conversion rate
        $conversionRate = $visitsThisMonth > 0
            ? round(($conversionsThisMonth / $visitsThisMonth) * 100, 1)
            : 0;

        return [
            Stat::make('Visitas este mes', number_format($visitsThisMonth))
                ->description($visitsTrend['description'])
                ->descriptionIcon($visitsTrend['icon'])
                ->color($visitsTrend['color'])
                ->chart($this->getSparklineData($tenantId, 'visits')),

            Stat::make('Clics en WhatsApp', number_format($conversionsThisMonth))
                ->description($conversionsTrend['description'])
                ->descriptionIcon($conversionsTrend['icon'])
                ->color($conversionsTrend['color'])
                ->chart($this->getSparklineData($tenantId, 'conversions')),

            Stat::make('Tasa de Conversión', "{$conversionRate}%")
                ->description('Visitas → WhatsApp')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($conversionRate >= 5 ? 'success' : ($conversionRate >= 2 ? 'warning' : 'gray')),
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
            $data = TenantVisit::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->pluck('count', 'date');
        } else {
            $data = Interaction::query()
                ->where('tenant_id', $tenantId)
                ->ofType(Interaction::TYPE_WHATSAPP_CLICK)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->pluck('count', 'date');
        }

        return $days->map(fn ($date) => $data[$date] ?? 0)->toArray();
    }

    private function getTenantRecord(): ?Tenant
    {
        $user = Auth::user();
        $tenant = $user?->tenant;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
