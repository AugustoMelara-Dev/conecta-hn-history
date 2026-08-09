<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class ChannelPerformanceChartWidget extends ChartWidget
{
    protected ?string $heading = 'Performance por canal';

    protected static ?int $sort = 19;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::ChannelPerformance, 'Ingresos por canal');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
