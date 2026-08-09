<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrdersChart extends ChartWidget
{
    protected ?string $heading = 'Pedidos últimos 30 días';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['datasets' => [], 'labels' => []];
        }

        $data = Trend::query(
            Order::query()->where('tenant_id', $tenant->getKey())
        )
            ->between(start: now()->subDays(30), end: now())
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Pedidos',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate)->toArray(),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
