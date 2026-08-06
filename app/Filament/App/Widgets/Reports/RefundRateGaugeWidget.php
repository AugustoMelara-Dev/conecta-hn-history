<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass RefundRateGaugeWidget extends ChartWidget
{
    protected static ?string $heading = 'Tasa de reembolso';
    protected static ?int $sort = 25;

    protected function getData(): array { return ['datasets' => [['data' => [2.3]]], 'labels' => ['Refund']]; }
    protected function getType(): string { return 'gauge'; }
}\n
