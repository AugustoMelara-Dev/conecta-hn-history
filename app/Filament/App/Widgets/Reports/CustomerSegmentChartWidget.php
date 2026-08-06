<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass CustomerSegmentChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Segmentos de clientes';
    protected static ?int $sort = 22;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'pie'; }
}\n
