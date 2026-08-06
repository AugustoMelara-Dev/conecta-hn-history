<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass InventoryHealthWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected static ?string $heading = 'Salud de inventario';
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return [];
        $lowStock = DB::table('products')->where('tenant_id', $tenantId)->where('stock', '<', 10)->count();
        return [Stat::make('Bajo stock', $lowStock)->color('warning')];
    }
}\n
