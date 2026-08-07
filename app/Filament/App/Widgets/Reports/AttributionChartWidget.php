<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Illuminate\Support\Facades\DB;

use Filament\Facades\Filament;

use Filament\Widgets\ChartWidget;

class AttributionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Atribución por fuente';
    protected static ?int $sort = 14;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return ['datasets' => [], 'labels' => []];
        $data = DB::table('orders as o')->join('crm_contacts as c', 'c.email', '=', 'o.customer_email')
            ->where('o.tenant_id', $tenantId)->where('o.status', 'paid')
            ->selectRaw('COALESCE(c.source, "direct") as source, SUM(o.total_amount) as revenue')
            ->groupBy('source')->get();
        return [
            'datasets' => [['data' => $data->pluck('revenue')->map(fn ($v) => (float) $v)->toArray(), 'backgroundColor' => ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']]],
            'labels' => $data->pluck('source')->toArray(),
        ];
    }

    protected function getType(): string { return 'doughnut'; }
}

