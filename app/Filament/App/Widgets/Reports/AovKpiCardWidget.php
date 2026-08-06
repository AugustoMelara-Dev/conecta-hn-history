<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass AovKpiCardWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected static ?string $heading = 'Ticket promedio';
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return [];
        $aov = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonth())->avg('total_amount') ?? 0;

        return [Stat::make('AOV', 'L ' . number_format((float) $aov, 2))->color('info')];
    }
}\n
