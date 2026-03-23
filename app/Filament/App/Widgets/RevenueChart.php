<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Order;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Ingresos por Ordenes (Ultimos 30 dias)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        /** @var array<string, float> $aggregates */
        $aggregates = Order::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('status', 'delivered')
            ->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(created_at) as order_date, COALESCE(SUM(total), 0) as aggregate')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->pluck('aggregate', 'order_date')
            ->map(fn ($value): float => (float) $value)
            ->all();

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');
            $data[] = $aggregates[$key] ?? 0.0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (L.)',
                    'data' => $data,
                    'borderColor' => '#000000',
                    'backgroundColor' => 'rgba(0, 0, 0, 0.1)',
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
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
