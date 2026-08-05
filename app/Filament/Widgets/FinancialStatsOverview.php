<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinancialStatsOverview extends BaseWidget
{
    /** @var array<int, string> */
    private const PAID_PLAN_KEYS = [
        TenantPlan::PRO->value,
        Tenant::PLAN_CRECIMIENTO,
        Tenant::PLAN_COSECHA,
    ];

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
        $proTenants = Tenant::whereIn('plan_type', self::PAID_PLAN_KEYS)
            ->where(function ($query) {
                $query->whereNull('subscription_ends_at')
                    ->orWhere('subscription_ends_at', '>', now());
            })
            ->count();

        $monthlyPlanPrice = $this->monthlyPlanPrice();
        $mrr = $proTenants * $monthlyPlanPrice;
        $approvedPayments = SubscriptionPayment::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('status', SubscriptionPayment::STATUS_APPROVED);

        $todayRevenue = (clone $approvedPayments)
            ->whereDate('created_at', today())
            ->sum('amount');

        $monthRevenue = (clone $approvedPayments)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $previousMonthRevenue = (clone $approvedPayments)
            ->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('amount');

        $totalTenants = Tenant::count();
        $conversionRate = $totalTenants > 0
            ? round(($proTenants / $totalTenants) * 100, 1)
            : 0;

        $pendingPayments = SubscriptionPayment::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->count();

        $failedStripeIntents = DB::table('subscription_intents')
            ->where('gateway_provider', 'stripe')
            ->where('status', 'FAILED')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $pastDueTenants = Tenant::query()
            ->where('subscription_status', 'past_due')
            ->count();

        $growth = $previousMonthRevenue > 0
            ? round(((float) $monthRevenue - (float) $previousMonthRevenue) / (float) $previousMonthRevenue * 100, 1)
            : ((float) $monthRevenue > 0 ? 100.0 : 0.0);

        return [
            Stat::make('MRR', $this->money($mrr))
                ->description($proTenants.' suscripciones activas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($proTenants > 0 ? 'success' : 'gray'),

            Stat::make('Revenue hoy', $this->money((float) $todayRevenue))
                ->description('Cobros aprobados desde medianoche')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color((float) $todayRevenue > 0 ? 'success' : 'gray'),

            Stat::make('Revenue mes', $this->money((float) $monthRevenue))
                ->description($growth.'% vs mes anterior')
                ->descriptionIcon($growth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growth >= 0 ? 'success' : 'danger'),

            Stat::make('Conversion PRO', $conversionRate.'%')
                ->description($proTenants.' de '.$totalTenants.' negocios son PRO')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('gray'),

            Stat::make('Pagos Pendientes', $pendingPayments)
                ->description('Requieren revision inmediata')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'gray'),

            Stat::make('Fallos Stripe 30d', $failedStripeIntents)
                ->description('Intents marcados como fallidos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedStripeIntents > 0 ? 'danger' : 'gray'),

            Stat::make('Past due', $pastDueTenants)
                ->description('Tenants con invoice fallida o suscripcion vencida')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color($pastDueTenants > 0 ? 'danger' : 'gray'),
        ];
    }

    private function monthlyPlanPrice(): float
    {
        return \App\Domain\TenantGovernance\Services\SubscriptionPricingService::canonicalProPriceInMajorUnits();
    }

    private function money(float $amount): string
    {
        return 'L. '.number_format($amount, 2);
    }
}
