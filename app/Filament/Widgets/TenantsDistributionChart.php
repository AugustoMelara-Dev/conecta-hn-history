<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class TenantsDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Tenants Distribution Chart';

    protected function getData(): array
    {
        $pro = \App\Models\Tenant::where('is_pro', true)->count();
        $free = \App\Models\Tenant::where('is_pro', false)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Distribución de Planes',
                    'data' => [$pro, $free],
                    'backgroundColor' => ['#fbbf24', '#9ca3af'], // Amber-400, Gray-400
                ],
            ],
            'labels' => ['Pro', 'Gratuito'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
