<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class RefundRateGaugeWidget extends ChartWidget
{
    protected ?string $heading = 'Tasa de reembolso';

    protected static ?int $sort = 25;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::RefundRate, 'Reembolsos', '#EF4444');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
