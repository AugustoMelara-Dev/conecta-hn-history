<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Application\Markets\TenantMarketResolver;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\VerifiedCommercialOutcome;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $market = app(TenantMarketResolver::class)->resolveOrNull($tenant);
        $currency = $market?->currencyCode;

        $ordersThisMonth = Order::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $revenueMinor = $currency === null ? null : (int) VerifiedCommercialOutcome::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('outcome', [
                CommercialOutcome::OrderPaid->value,
                CommercialOutcome::OrderRefunded->value,
            ])
            ->where('currency', $currency)
            ->whereYear('source_occurred_at', now()->year)
            ->whereMonth('source_occurred_at', now()->month)
            ->sum('amount_minor');
        $revenueLabel = $revenueMinor === null
            ? 'No disponible'
            : $currency.' '.number_format($revenueMinor / 100, 2);

        $customers = CustomerProfile::query()
            ->where('tenant_id', $tenant->getKey())
            ->count();

        return [
            Stat::make('Pedidos del mes', $ordersThisMonth)
                ->description('Total de pedidos en el mes actual')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Ingresos del mes', $revenueLabel)
                ->description($revenueMinor === null
                    ? 'Configura el mercado principal para consolidar ingresos'
                    : 'Pagos confirmados menos reembolsos del mes actual')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Clientes', $customers)
                ->description('Clientes registrados')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
