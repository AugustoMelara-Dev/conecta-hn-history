<?php

declare(strict_types=1);
use App\Filament\Admin\Widgets\AiCostByTenantChartWidget;
use App\Filament\Admin\Widgets\DiskAndQueueWidget;
use App\Filament\Admin\Widgets\GlobalRevenueChartWidget;
use App\Filament\Admin\Widgets\PlanDistributionChartWidget;
use App\Filament\Admin\Widgets\RecentIncidentsWidget;
use App\Filament\Admin\Widgets\SystemHealthWidget;
use App\Filament\Admin\Widgets\TenantsOverviewWidget;
use App\Filament\Admin\Widgets\TopTenantsTableWidget;

it('8 admin widgets exist', function () {
    $widgets = [
        TenantsOverviewWidget::class,
        GlobalRevenueChartWidget::class,
        PlanDistributionChartWidget::class,
        TopTenantsTableWidget::class,
        SystemHealthWidget::class,
        RecentIncidentsWidget::class,
        AiCostByTenantChartWidget::class,
        DiskAndQueueWidget::class,
    ];
    expect(count($widgets))->toBe(8);
    foreach ($widgets as $w) {
        expect(class_exists($w))->toBeTrue();
    }
});
