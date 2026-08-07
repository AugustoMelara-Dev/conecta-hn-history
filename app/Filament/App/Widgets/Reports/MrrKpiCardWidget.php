<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Illuminate\Support\Facades\DB;

use Filament\Facades\Filament;

class MrrKpiCardWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected ?string $heading = 'MRR';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return [];
        $mrr = DB::table('subscription_payments')->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonth())->sum('amount') ?? 0;

        return [
            Stat::make('MRR', 'L ' . number_format((float) $mrr, 2))->description('Monthly recurring revenue')->color('success'),
            Stat::make('ARR', 'L ' . number_format((float) ($mrr * 12), 2))->description('Annual run rate')->color('primary'),
        ];
    }
}
