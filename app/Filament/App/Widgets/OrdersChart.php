<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Appointment;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

/**
 * OrdersChart - 7-Day Appointment Volume
 *
 * Bar chart showing daily appointment counts for quick
 * visualization of booking trends.
 *
 * SECURITY: Data is strictly scoped to current tenant.
 */
class OrdersChart extends ChartWidget
{
    protected ?string $heading = 'Citas por Día (Última Semana)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $data = Trend::query(
            Appointment::query()
                ->where('tenant_id', $tenant->id)
        )
            ->dateColumn('scheduled_at')
            ->between(
                start: now()->subDays(7),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Citas',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#000000',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
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
