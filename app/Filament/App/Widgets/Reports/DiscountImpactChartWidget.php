<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass DiscountImpactChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Impacto de descuentos';
    protected static ?int $sort = 24;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}\n
