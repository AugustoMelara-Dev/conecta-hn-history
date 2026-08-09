<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class ChannelPerformanceChartWidget extends ChartWidget
{
    protected ?string $heading = 'Performance por canal';

    protected static ?int $sort = 19;

    protected function getData(): array
    {
        return ['datasets' => [], 'labels' => []];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
