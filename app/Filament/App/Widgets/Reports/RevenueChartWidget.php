<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Ingresos por mes';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::RevenueByMonth, 'Ingresos');
    }

    protected function getType(): string
    {
        return 'line';
    }
}
