<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class CustomerSegmentChartWidget extends ChartWidget
{
    protected ?string $heading = 'Segmentos de clientes';

    protected static ?int $sort = 22;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::CustomerSegments, 'Clientes');
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
