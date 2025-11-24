<?php

namespace App\Filament\Widgets;

use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Usuarios', User::count())
                ->description('Clientes y Vendedores')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
            Stat::make('Negocios Registrados', Tenant::count())
                ->description('Activos y Pendientes')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),
            Stat::make('Ciudades Cubiertas', City::has('tenants')->count())
                ->description('Con al menos un negocio')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning'),
        ];
    }
}
