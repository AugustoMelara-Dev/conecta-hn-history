<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class ConversionFunnelChartWidget extends ChartWidget
{
    protected ?string $heading = 'Embudo de conversión';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::ConversionFunnel, 'Contactos');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
