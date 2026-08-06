<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass NpsScoreWidget extends ChartWidget
{
    protected static ?string $heading = 'NPS Score';
    protected static ?int $sort = 12;

    protected function getData(): array { return ['datasets' => [['data' => [72]]], 'labels' => ['NPS']]; }
    protected function getType(): string { return 'gauge'; }
}\n
