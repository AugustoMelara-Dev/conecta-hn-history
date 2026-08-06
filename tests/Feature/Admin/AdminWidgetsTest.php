<?php

declare(strict_types=1);

it('8 admin widgets exist', function () {
    $widgets = [
        \App\Filament\Admin\Widgets\TenantsOverviewWidget::class,
        \App\Filament\Admin\Widgets\GlobalRevenueChartWidget::class,
        \App\Filament\Admin\Widgets\PlanDistributionChartWidget::class,
        \App\Filament\Admin\Widgets\TopTenantsTableWidget::class,
        \App\Filament\Admin\Widgets\SystemHealthWidget::class,
        \App\Filament\Admin\Widgets\RecentIncidentsWidget::class,
        \App\Filament\Admin\Widgets\AiCostByTenantChartWidget::class,
        \App\Filament\Admin\Widgets\DiskAndQueueWidget::class,
    ];
    expect(count($widgets))->toBe(8);
    foreach ($widgets as $w) {
        expect(class_exists($w))->toBeTrue();
    }
});
