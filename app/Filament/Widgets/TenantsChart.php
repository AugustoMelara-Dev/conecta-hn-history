<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class TenantsChart extends ChartWidget
{
    protected ?string $heading = 'Crecimiento de Negocios';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Tenant::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        // Fill missing months
        $counts = [];
        for ($i = 1; $i <= 12; $i++) {
            $counts[] = $data[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nuevos Negocios',
                    'data' => $counts,
                    'borderColor' => '#6366f1',
                    'fill' => 'start',
                ],
            ],
            'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
