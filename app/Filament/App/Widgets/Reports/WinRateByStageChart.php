<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class WinRateByStageChart extends ChartWidget
{
    protected ?string $heading = 'Win Rate por Stage';
    protected static ?int $sort = 17;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}

