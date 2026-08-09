<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ActivityVolumeChartWidget extends ChartWidget
{
    protected ?string $heading = 'Volumen de actividades';

    protected static ?int $sort = 18;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return ['datasets' => [], 'labels' => []];
        }
        $data = DB::table('crm_activities')->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')->orderBy('date')->get();

        return [
            'datasets' => [['label' => 'Actividades', 'data' => $data->pluck('cnt')->map(fn ($v) => (int) $v)->toArray(), 'borderColor' => '#10B981', 'backgroundColor' => 'rgba(16,185,129,0.1)', 'fill' => true, 'tension' => 0.3]],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
