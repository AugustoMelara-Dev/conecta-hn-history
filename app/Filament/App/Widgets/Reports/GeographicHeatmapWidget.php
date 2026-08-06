<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass GeographicHeatmapWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribución geográfica';
    protected static ?int $sort = 21;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}\n
