<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class FulfillmentSlaWidget extends ChartWidget
{
    protected ?string $heading = 'SLA Fulfillment';

    protected static ?int $sort = 11;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::FulfillmentSla, 'SLA', '#10B981');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
