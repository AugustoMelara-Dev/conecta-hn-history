<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass LeadScoreDistribution extends ChartWidget
{
    protected static ?string $heading = 'Lead Score Distribution';
    protected static ?int $sort = 16;

    protected function getData(): array { return ['datasets' => [], 'labels' => ['Hot', 'Warm', 'Qualified', 'Cold']]; }
    protected function getType(): string { return 'pie'; }
}\n
