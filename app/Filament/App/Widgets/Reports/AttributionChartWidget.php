<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class AttributionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Atribución por fuente';

    protected static ?int $sort = 14;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::AttributionBySource, 'Ingresos');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
