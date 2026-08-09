<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\CustomerProfile;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [];
        }

        $ordersThisMonth = Order::query()
            ->where('business_id', $tenant->getKey())
            ->whereMonth('created_at', now()->month)
            ->count();

        $revenueThisMonth = Order::query()
            ->where('business_id', $tenant->getKey())
            ->whereMonth('created_at', now()->month)
            ->where('status', 'paid')
            ->sum('total_amount') ?? 0;

        $customers = CustomerProfile::query()
            ->where('business_id', $tenant->getKey())
            ->count();

        return [
            Stat::make('Pedidos del mes', $ordersThisMonth)
                ->description('Total de pedidos en el mes actual')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Ingresos del mes', 'L '.number_format((float) $revenueThisMonth, 2))
                ->description('Ingresos generados en el mes actual')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Clientes', $customers)
                ->description('Clientes registrados')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
