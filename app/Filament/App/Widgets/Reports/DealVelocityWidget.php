<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class DealVelocityWidget extends ChartWidget
{
    protected ?string $heading = 'Velocidad de deals';

    protected static ?int $sort = 15;

    protected function getData(): array
    {
        return ['datasets' => [], 'labels' => []];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
