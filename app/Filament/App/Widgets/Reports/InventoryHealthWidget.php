<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

class InventoryHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Salud de inventario';

    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return [];
        }
        $lowStock = DB::table('products')->where('tenant_id', $tenantId)->where('stock', '<', 10)->count();

        return [Stat::make('Bajo stock', $lowStock)->color('warning')];
    }
}
