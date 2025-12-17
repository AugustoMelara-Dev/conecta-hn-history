<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    /**
     * Control de Visibilidad por Rol
     * Solo super_admin y auditor pueden ver widgets financieros
     * El rol 'soporte' NO debe ver informacion financiera
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        // Soporte no puede ver widgets financieros
        if ($user->hasRole('soporte')) return false;
        
        // super_admin, auditor, o admin@conectahn.com pueden ver
        return $user->hasRole(['super_admin', 'auditor']) || $user->email === 'admin@conectahn.com';
    }

    protected function getStats(): array
    {
        $proTenants = Tenant::where('is_pro', true)
            ->where(function ($query) {
                $query->whereNull('plan_expires_at')
                    ->orWhere('plan_expires_at', '>', now());
            })
            ->count();

        $monthlyPlanPrice = 500.00;
        $mrr = $proTenants * $monthlyPlanPrice;

        $totalRevenue = Payment::where('status', 'approved')->sum('amount');

        $totalTenants = Tenant::count();
        $conversionRate = $totalTenants > 0
            ? round(($proTenants / $totalTenants) * 100, 1)
            : 0;

        $pendingPayments = Payment::where('status', 'pending')->count();

        return [
            Stat::make('MRR (Ingreso Mensual Recurrente)', 'L. ' . number_format($mrr, 2))
                ->description($proTenants . ' suscripciones activas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Ingresos Brutos Totales', 'L. ' . number_format($totalRevenue, 2))
                ->description('Flujo de caja historico')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Tasa de Conversion', $conversionRate . '%')
                ->description($proTenants . ' de ' . $totalTenants . ' negocios son PRO')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($conversionRate >= 5 ? 'success' : ($conversionRate >= 2 ? 'warning' : 'danger')),

            Stat::make('Pagos Pendientes', $pendingPayments)
                ->description('Requieren revision inmediata')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'success'),
        ];
    }
}
