<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AcquisitionGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'Curva de Crecimiento de Adquisicion';

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
        $startDate = now()->subDays($days);

        // Build daily counts manually without Flowframe/Trend
        $data = collect();
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $count = Tenant::whereDate('created_at', $date->toDateString())->count();
            $data->push(['date' => $date->format('d/m'), 'count' => $count]);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nuevos Negocios',
                    'data' => $data->pluck('count'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->pluck('date'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

