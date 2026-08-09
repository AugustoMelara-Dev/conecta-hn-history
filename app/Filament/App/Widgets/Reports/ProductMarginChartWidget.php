<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class ProductMarginChartWidget extends ChartWidget
{
    protected ?string $heading = 'Margen por producto';

    protected static ?int $sort = 20;

    protected function getData(): array
    {
        return ['datasets' => [], 'labels' => []];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
