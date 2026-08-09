<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class GeographicHeatmapWidget extends ChartWidget
{
    protected ?string $heading = 'Distribución geográfica';

    protected static ?int $sort = 21;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::GeographicDistribution, 'Clientes');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
