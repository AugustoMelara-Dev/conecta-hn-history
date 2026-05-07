<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class PlanDistributionChart extends ChartWidget
{
    /** @var array<int, string> */
    private const PAID_PLAN_KEYS = [
        TenantPlan::PRO->value,
        Tenant::PLAN_CRECIMIENTO,
        Tenant::PLAN_COSECHA,
    ];

    protected static ?string $heading = 'Distribucion de Cartera';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $proCount = Tenant::whereIn('plan_type', self::PAID_PLAN_KEYS)->count();
        $freeCount = Tenant::where('plan_type', Tenant::PLAN_SEMILLA)->count();

        $total = $proCount + $freeCount;
        $proPercent = $total > 0 ? round(($proCount / $total) * 100, 1) : 0;
        $freePercent = $total > 0 ? round(($freeCount / $total) * 100, 1) : 0;

        return [
            'datasets' => [
                [
                    'label' => 'Negocios',
                    'data' => [$proCount, $freeCount],
                    'backgroundColor' => ['#000000', '#e5e7eb'],
                    'borderColor' => ['#000000', '#d1d5db'],
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
