<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Filament\App\Widgets\Reports;\n\nclass CampaignRoiBarChartWidget extends ChartWidget
{
    protected static ?string $heading = 'ROI de campañas';
    protected static ?int $sort = 7;

    protected function getData(): array
    {
        return ['datasets' => [], 'labels' => []];
    }

    protected function getType(): string { return 'bar'; }
}\n
