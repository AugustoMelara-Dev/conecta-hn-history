<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class ChurnGaugeWidget extends ChartWidget
{
    protected ?string $heading = 'Tasa de churn';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::ChurnRate, 'Churn', '#EF4444');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
