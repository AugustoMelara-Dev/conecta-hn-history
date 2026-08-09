<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LtvKpiCardWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'LTV';

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $data = ReportWidgetData::report(ReportKey::CustomerLtv);
        if ($data === null) {
            return [];
        }
        $values = array_map(static fn ($row): float => (float) $row->value, $data->rows);
        $avg = $values === [] ? 0.0 : array_sum($values) / count($values);

        return [
            Stat::make('LTV promedio', 'L '.number_format((float) $avg, 2))->description('Customer lifetime value'),
        ];
    }
}
