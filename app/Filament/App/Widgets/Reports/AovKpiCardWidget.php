<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AovKpiCardWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Ticket promedio';

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $data = ReportWidgetData::report(ReportKey::AvgOrderValue);
        if ($data === null) {
            return [];
        }
        $aov = (float) ($data->summary['current_aov'] ?? 0);

        return [Stat::make('AOV', 'L '.number_format((float) $aov, 2))->color('info')];
    }
}
