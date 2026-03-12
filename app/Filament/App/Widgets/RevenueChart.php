<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Appointment;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

/**
 * RevenueChart - 30-Day Estimated Revenue Trend
 *
 * ARCHITECTURE NOTE: Revenue is calculated using current Service.price
 * since Appointment model does not snapshot price at booking time.
 * This is an MVP trade-off for simplicity.
 *
 * SECURITY: Data is strictly scoped to current tenant.
 */
class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Ingresos Estimados (Últimos 30 días)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $data = Trend::query(
            Appointment::query()
                ->where('appointments.tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->select('appointments.*')
        )
            ->dateColumn('appointments.scheduled_at')
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->sum('services.price');

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (L.)',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
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
