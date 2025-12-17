<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class AcquisitionGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Curva de Crecimiento de Adquisicion';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '30' => 'Ultimos 30 dias',
            '60' => 'Ultimos 60 dias',
            '90' => 'Ultimos 90 dias',
        ];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;

        $data = Trend::model(Tenant::class)
            ->between(
                start: now()->subDays($days),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Nuevos Negocios',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
