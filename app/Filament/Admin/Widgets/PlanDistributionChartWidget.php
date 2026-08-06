<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class PlanDistributionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribución de planes';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $counts = Tenant::selectRaw('plan, COUNT(*) as count')->groupBy('plan')->get();
        return [
            'datasets' => [[
                'data' => $counts->pluck('count')->toArray(),
                'backgroundColor' => ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'],
            ]],
            'labels' => $counts->pluck('plan')->toArray(),
        ];
    }

    protected function getType(): string { return 'doughnut'; }
}
