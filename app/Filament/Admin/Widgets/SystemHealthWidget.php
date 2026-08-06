<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SystemHealthWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $jobs = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        return [
            Stat::make('Jobs en cola', $jobs)->color($jobs > 100 ? 'warning' : 'success'),
            Stat::make('Jobs fallidos', $failed)->color($failed > 0 ? 'danger' : 'success'),
            Stat::make('Memoria', round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB'),
            Stat::make('DB conexiones', DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 'n/a'),
        ];
    }
}
