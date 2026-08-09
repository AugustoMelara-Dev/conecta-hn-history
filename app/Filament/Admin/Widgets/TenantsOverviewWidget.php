<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Tenant::count();
        $active = Tenant::where('is_active', true)->count();
        $pro = Tenant::where('plan', 'pro')->count();
        $enterprise = Tenant::where('plan', 'enterprise')->count();

        return [
            Stat::make('Total tenants', $total)->description('Registrados')->color('primary'),
            Stat::make('Activos', $active)->description('Activos ahora')->color('success'),
            Stat::make('Plan Pro', $pro)->color('info'),
            Stat::make('Enterprise', $enterprise)->color('warning'),
        ];
    }
}
