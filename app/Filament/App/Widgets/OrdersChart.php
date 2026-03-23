<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Order;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Ordenes por Dia (Ultima Semana)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        /** @var array<string, int> $aggregates */
        $aggregates = Order::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as aggregate')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->pluck('aggregate', 'order_date')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');
            $data[] = $aggregates[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ordenes',
                    'data' => $data,
                    'backgroundColor' => '#000000',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
