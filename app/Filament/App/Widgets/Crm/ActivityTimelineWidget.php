<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Crm;

use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ActivityTimelineWidget extends ChartWidget
{
    protected ?string $heading = 'Timeline de actividades (30 días)';

    protected static ?int $sort = 23;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return ['datasets' => [], 'labels' => []];
        }

        $activities = DB::table('crm_activities')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, type, COUNT(*) as cnt')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        $byDate = [];
        $byType = [];
        foreach ($activities as $a) {
            $byDate[$a->date] = true;
            $byType[$a->type] = true;
        }
        $labels = array_keys($byDate);
        $types = array_keys($byType);
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];

        $datasets = [];
        foreach ($types as $i => $type) {
            $values = [];
            foreach ($labels as $date) {
                $count = $activities->first(fn ($a) => $a->date === $date && $a->type === $type)?->cnt ?? 0;
                $values[] = (int) $count;
            }
            $datasets[] = [
                'label' => $type,
                'data' => $values,
                'borderColor' => $colors[$i % count($colors)],
                'backgroundColor' => 'transparent',
                'tension' => 0.3,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
