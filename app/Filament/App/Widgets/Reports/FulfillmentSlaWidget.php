<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class FulfillmentSlaWidget extends ChartWidget
{
    protected ?string $heading = 'SLA Fulfillment';

    protected static ?int $sort = 11;

    protected function getData(): array
    {
        return ['datasets' => [['data' => [95]]], 'labels' => ['SLA']];
    }

    protected function getType(): string
    {
        return 'gauge';
    }
}
