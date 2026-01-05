<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class PlanDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribucion de Cartera';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $proCount = Tenant::where('is_pro', true)->count();
        $freeCount = Tenant::where('is_pro', false)->count();

        $total = $proCount + $freeCount;
        $proPercent = $total > 0 ? round(($proCount / $total) * 100, 1) : 0;
        $freePercent = $total > 0 ? round(($freeCount / $total) * 100, 1) : 0;

        return [
            'datasets' => [
                [
                    'label' => 'Negocios',
                    'data' => [$proCount, $freeCount],
                    'backgroundColor' => ['#10b981', '#64748b'],
                    'borderColor' => ['#059669', '#475569'],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => [
                "PRO ({$proPercent}%)",
                "Gratuitos ({$freePercent}%)",
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
