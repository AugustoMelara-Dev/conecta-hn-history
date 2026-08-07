<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Crm;

use App\Domain\Crm\Enums\LifecycleStage;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ConversionFunnelWidget extends ChartWidget
{
    protected ?string $heading = 'Embudo de conversión';
    protected static ?int $sort = 22;

    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return ['datasets' => [], 'labels' => []];

        $stages = [
            LifecycleStage::Lead->value,
            LifecycleStage::Qualified->value,
            LifecycleStage::Proposal->value,
            LifecycleStage::Negotiation->value,
            LifecycleStage::Won->value,
        ];
        $counts = [];
        foreach ($stages as $stage) {
            $counts[] = DB::table('crm_contacts')->where('tenant_id', $tenantId)->where('lifecycle_stage', $stage)->count();
        }

        return [
            'datasets' => [[
                'label' => 'Contactos',
                'data' => $counts,
                'backgroundColor' => ['#9CA3AF', '#3B82F6', '#F59E0B', '#6366F1', '#10B981'],
            ]],
            'labels' => array_map(fn ($s) => ucfirst($s), $stages),
        ];
    }

    protected function getType(): string { return 'bar'; }
}
