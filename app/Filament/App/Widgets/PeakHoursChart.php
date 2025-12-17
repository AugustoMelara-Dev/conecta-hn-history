<?php

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * PeakHoursChart - Gráfico de Horas Pico
 * 
 * Filament v4 Compatible: Extiende ChartWidget que maneja propiedades internamente.
 */
class PeakHoursChart extends ChartWidget
{
    protected static ?string $heading = 'Horarios de Mayor Tráfico';

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
        
        if (!$tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $hourlyData = Interaction::where('tenant_id', $tenant->id)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 24; $i++) {
            $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
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
