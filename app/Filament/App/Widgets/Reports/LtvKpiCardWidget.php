<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Illuminate\Support\Facades\DB;

use Filament\Facades\Filament;

class LtvKpiCardWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected ?string $heading = 'LTV';
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return [];
        $avg = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'paid')
            ->selectRaw('customer_email, SUM(total_amount) as total')
            ->groupBy('customer_email')
            ->get()->avg('total') ?? 0;

        return [
            Stat::make('LTV promedio', 'L ' . number_format((float) $avg, 2))->description('Customer lifetime value'),
        ];
    }
}
