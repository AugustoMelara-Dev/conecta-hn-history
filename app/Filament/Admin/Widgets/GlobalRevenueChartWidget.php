<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GlobalRevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Ingresos globales (12 meses)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = DB::table('orders')
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, SUM(total_amount) as revenue")
            ->groupBy('month')->orderBy('month')->get();

        return [
            'datasets' => [[
                'label' => 'Revenue',
                'data' => $data->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(),
                'borderColor' => '#10B981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
