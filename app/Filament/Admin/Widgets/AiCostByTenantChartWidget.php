<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AiCostByTenantChartWidget extends ChartWidget
{
    protected ?string $heading = 'AI Cost por Tenant';
    protected static ?int $sort = 7;

    protected function getData(): array
    {
        $data = DB::table('ai_assistant_runs')
            ->selectRaw('tenant_id, SUM(cost_usd) as total_cost')
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get()
            ->map(function ($r) {
                $name = DB::table('tenants')->where('id', $r->tenant_id)->value('name') ?? 'Unknown';
                return (object) ['name' => $name, 'cost' => (float) $r->total_cost];
            });

        return [
            'datasets' => [[
                'label' => 'Costo USD',
                'data' => $data->pluck('cost')->toArray(),
                'backgroundColor' => '#F59E0B',
            ]],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string { return 'bar'; }
}
