<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperAdminStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Nuevos Negocios (Mes)', \App\Models\Tenant::whereMonth('created_at', now()->month)->count())
                ->description('Crecimiento mensual')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('Tráfico Total', \App\Models\Interaction::where('type', 'view')->count())
                ->description('Visitas a micrositios')
                ->color('primary'),

            Stat::make('Leads Generados', \App\Models\Lead::count())
                ->description('Oportunidades de venta')
                ->color('warning'),
        ];
    }
}
