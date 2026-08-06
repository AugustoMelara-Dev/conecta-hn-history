<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass FulfillmentSlaWidget extends ChartWidget
{
    protected static ?string $heading = 'SLA Fulfillment';
    protected static ?int $sort = 11;

    protected function getData(): array { return ['datasets' => [['data' => [95]]], 'labels' => ['SLA']]; }
    protected function getType(): string { return 'gauge'; }
}\n
