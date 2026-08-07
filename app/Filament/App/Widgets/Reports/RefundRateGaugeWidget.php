<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class RefundRateGaugeWidget extends ChartWidget
{
    protected ?string $heading = 'Tasa de reembolso';
    protected static ?int $sort = 25;

    protected function getData(): array { return ['datasets' => [['data' => [2.3]]], 'labels' => ['Refund']]; }
    protected function getType(): string { return 'gauge'; }
}
