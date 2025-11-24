<?php

namespace App\Filament\App\Resources\FollowerResource\Pages;

use App\Filament\App\Resources\FollowerResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListFollowers extends ListRecords
{
    protected static string $resource = FollowerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            FollowerCountWidget::class,
        ];
    }
}

class FollowerCountWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenant = Filament::getTenant();
        $count = $tenant->followers()->count();

        return [
            Stat::make('Total de Seguidores', $count)
                ->description('Usuarios que siguen tu negocio')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
