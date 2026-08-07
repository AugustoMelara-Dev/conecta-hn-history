<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class CohortHeatmapWidget extends ChartWidget
{
    protected ?string $heading = 'Retención por cohorte';
    protected static ?int $sort = 13;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}

