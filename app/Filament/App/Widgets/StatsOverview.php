<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    public static function getSort(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $tenantId = (string) $tenant->getKey();
        $views = Interaction::query()->where('tenant_id', $tenantId)->where('type', 'view')->count();
        $clicks = Interaction::query()->where('tenant_id', $tenantId)->where('type', 'whatsapp_click')->count();
        $conversionRate = $views > 0 ? round(($clicks / $views) * 100, 1) : 0;
        $pendingOrders = Order::query()->where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $totalRevenue = Order::query()->where('tenant_id', $tenantId)->where('status', 'delivered')->sum('total');

        return [
            Stat::make('Ingresos Totales', 'L. '.number_format((float) $totalRevenue, 2))
                ->description('Órdenes entregadas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),
            Stat::make('Pedidos Pendientes', $pendingOrders)
                ->description('Requieren tu atención')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 0 ? 'warning' : 'gray'),
            Stat::make('Visitas Totales', $views)
                ->description('Vistas de tu página')
                ->descriptionIcon('heroicon-m-eye')
                ->color('gray'),
            Stat::make('Tasa de conversión', $conversionRate.'%')
                ->description('De visitas a clics')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('gray'),
        ];
    }
}
