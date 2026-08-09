<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiskAndQueueWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected function getStats(): array
    {
        $diskFree = round(disk_free_space('/') / 1024 / 1024 / 1024, 2);
        $diskTotal = round(disk_total_space('/') / 1024 / 1024 / 1024, 2);
        $diskUsedPct = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        return [
            Stat::make('Disco libre', $diskFree.' GB')
                ->description(round($diskTotal, 1).' GB total, '.$diskUsedPct.'% usado')
                ->color($diskUsedPct > 80 ? 'danger' : 'success'),
            Stat::make('Memoria PHP', round(memory_get_peak_usage(true) / 1024 / 1024, 1).' MB'),
            Stat::make('PHP version', PHP_VERSION),
            Stat::make('Laravel', app()->version()),
        ];
    }
}
