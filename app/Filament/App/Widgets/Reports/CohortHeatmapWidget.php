<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass CohortHeatmapWidget extends ChartWidget
{
    protected static ?string $heading = 'Retención por cohorte';
    protected static ?int $sort = 13;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}\n
