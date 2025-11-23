<?php

namespace App\Filament\App\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if (!$tenant) {
            return [];
        }

        $views = \App\Models\Interaction::where('tenant_id', $tenant->id)
            ->where('type', 'view')
            ->count();

        $clicks = \App\Models\Interaction::where('tenant_id', $tenant->id)
            ->where('type', 'whatsapp_click')
            ->count();

        $conversionRate = $views > 0 ? round(($clicks / $views) * 100, 1) : 0;

        return [
            Stat::make('Visitas Totales', $views)
                ->description('Vistas de tu página')
                ->descriptionIcon('heroicon-m-eye')
                ->color('gray'),
            Stat::make('Clics en WhatsApp', $clicks)
                ->description('Intenciones de compra')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),
            Stat::make('Tasa de Conversión', $conversionRate . '%')
                ->description('De visitas a clics')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
        ];
    }
}
