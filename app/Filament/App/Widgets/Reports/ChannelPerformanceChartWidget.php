<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass ChannelPerformanceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Performance por canal';
    protected static ?int $sort = 19;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}\n
