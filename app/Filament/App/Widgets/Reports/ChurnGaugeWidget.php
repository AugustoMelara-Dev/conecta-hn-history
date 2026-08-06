<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass ChurnGaugeWidget extends ChartWidget
{
    protected static ?string $heading = 'Tasa de churn';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        return [
            'datasets' => [['data' => [3.5], 'backgroundColor' => ['#EF4444']]],
            'labels' => ['Churn'],
        ];
    }

    protected function getType(): string { return 'doughnut'; }
}\n
