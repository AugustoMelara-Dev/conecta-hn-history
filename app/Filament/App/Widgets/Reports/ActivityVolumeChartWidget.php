<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class ActivityVolumeChartWidget extends ChartWidget
{
    protected ?string $heading = 'Volumen de actividades';

    protected static ?int $sort = 18;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::ActivityVolume, 'Actividades', '#10B981');
    }

    protected function getType(): string
    {
        return 'line';
    }
}
