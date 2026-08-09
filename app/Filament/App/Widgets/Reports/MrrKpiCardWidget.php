<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MrrKpiCardWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'MRR';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $data = ReportWidgetData::report(ReportKey::MrrArr);
        if ($data === null) {
            return [];
        }
        $mrr = (float) ($data->summary['current_mrr'] ?? 0);

        return [
            Stat::make('MRR', 'L '.number_format((float) $mrr, 2))->description('Monthly recurring revenue')->color('success'),
            Stat::make('ARR', 'L '.number_format((float) ($mrr * 12), 2))->description('Annual run rate')->color('primary'),
        ];
    }
}
