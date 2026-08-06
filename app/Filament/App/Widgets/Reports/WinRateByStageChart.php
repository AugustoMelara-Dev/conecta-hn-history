<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass WinRateByStageChart extends ChartWidget
{
    protected static ?string $heading = 'Win Rate por Stage';
    protected static ?int $sort = 17;

    protected function getData(): array { return ['datasets' => [], 'labels' => []]; }
    protected function getType(): string { return 'bar'; }
}\n
