<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

/**
 * RevenueChart - 30-Day Revenue Trend
 *
 * SECURITY: Data is strictly scoped to current tenant.
 */
class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Ingresos por Ordenes (Ultimos 30 dias)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $data = Trend::query(
            Order::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'delivered')
        )
            ->dateColumn('created_at')
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->sum('total');

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (L.)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#000000',
                    'backgroundColor' => 'rgba(0, 0, 0, 0.1)',
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
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
