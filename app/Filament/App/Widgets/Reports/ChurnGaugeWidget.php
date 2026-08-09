<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class ChurnGaugeWidget extends ChartWidget
{
    protected ?string $heading = 'Tasa de churn';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        return [
            'datasets' => [['data' => [3.5], 'backgroundColor' => ['#EF4444']]],
            'labels' => ['Churn'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
