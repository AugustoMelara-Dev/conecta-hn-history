<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class AcquisitionGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Curva de crecimiento de adquisición';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '30' => 'Últimos 30 días',
            '60' => 'Últimos 60 días',
            '90' => 'Últimos 90 días',
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
                    'label' => 'Nuevos negocios',
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
