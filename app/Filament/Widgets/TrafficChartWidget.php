<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Models\TenantVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/**
 * TrafficChartWidget - Daily visits line chart
 *
 * Shows traffic trends over the current month.
 * Uses Chart.js via Filament's native ChartWidget.
 */
class TrafficChartWidget extends ChartWidget
{
    protected ?string $heading = 'Tráfico del Mes';

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get all days of current month
        $daysInMonth = now()->daysInMonth;
        $labels = collect(range(1, $daysInMonth))->map(fn ($day) => (string) $day)->toArray();

        // ═══════════════════════════════════════════════════════════════
        // AGGREGATE VISITS BY DAY
        // Efficient single query with GROUP BY
        // ═══════════════════════════════════════════════════════════════
        $visitsData = TenantVisit::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->thisMonth()
            ->selectRaw('DAY(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        // Fill missing days with zeros
        $data = collect(range(1, $daysInMonth))
            ->map(fn ($day) => $visitsData[$day] ?? 0)
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Visitas',
                    'data' => $data,
                    'fill' => true,
                    'backgroundColor' => 'rgba(37, 99, 235, 0.1)',
                    'borderColor' => 'rgb(37, 99, 235)',
                    'tension' => 0.4,
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

    private function getTenantRecord(): ?Tenant
    {
        $user = Auth::user();
        $tenant = $user?->tenant;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
