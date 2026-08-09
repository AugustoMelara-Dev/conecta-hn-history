<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class NpsScoreWidget extends ChartWidget
{
    protected ?string $heading = 'NPS Score';

    protected static ?int $sort = 12;

    protected function getData(): array
    {
        return ['datasets' => [['data' => [72]]], 'labels' => ['NPS']];
    }

    protected function getType(): string
    {
        return 'gauge';
    }
}
