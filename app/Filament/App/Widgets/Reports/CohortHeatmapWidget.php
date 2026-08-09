<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class CohortHeatmapWidget extends ChartWidget
{
    protected ?string $heading = 'Retención por cohorte';

    protected static ?int $sort = 13;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::CohortRetention, 'Retención');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
