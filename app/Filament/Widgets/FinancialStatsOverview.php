<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * Control de visibilidad por rol.
     * Solo super_admin y auditor pueden ver widgets financieros.
     */
    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $proTenants = Tenant::where('is_pro', true)
            ->where(function ($query) {
                $query->whereNull('plan_expires_at')
                    ->orWhere('plan_expires_at', '>', now());
            })
            ->count();

        try {
            $settings = app(GeneralSettings::class);
            $monthlyPlanPrice = (float) ($settings->pro_plan_price ?? 0);
        } catch (\Throwable) {
            $monthlyPlanPrice = 0.0;
        }

        $mrr = $proTenants * $monthlyPlanPrice;

        $totalRevenue = SubscriptionPayment::where('status', 'approved')->sum('amount');

        $totalTenants = Tenant::count();
        $conversionRate = $totalTenants > 0
            ? round(($proTenants / $totalTenants) * 100, 1)
            : 0;

        $pendingPayments = SubscriptionPayment::where('status', 'pending')->count();

        return [
            Stat::make('MRR (Ingreso Mensual Recurrente)', 'L. ' . number_format($mrr, 2))
                ->description($proTenants . ' suscripciones activas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('gray'),

            Stat::make('Ingresos Brutos Totales', 'L. ' . number_format((float) $totalRevenue, 2))
                ->description('Flujo de caja historico')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),

            Stat::make('Tasa de Conversion', $conversionRate . '%')
                ->description($proTenants . ' de ' . $totalTenants . ' negocios son PRO')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('gray'),

            Stat::make('Pagos Pendientes', $pendingPayments)
                ->description('Requieren revision inmediata')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'gray'),
        ];
    }
}
