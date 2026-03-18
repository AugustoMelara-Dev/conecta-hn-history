<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class PeakHoursChart extends ChartWidget
{
    protected ?string $heading = 'Horarios de Mayor Trafico';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 20;
    }

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        /** @var array<int, int> $hourlyData */
        $hourlyData = Interaction::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 24; $i++) {
            $labels[] = str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00';
            $data[] = $hourlyData[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Interacciones',
                    'data' => $data,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
