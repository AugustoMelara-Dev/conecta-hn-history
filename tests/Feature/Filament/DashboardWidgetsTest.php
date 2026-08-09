<?php

declare(strict_types=1);

use App\Filament\App\Widgets\OperationalCockpitWidget;
use App\Filament\App\Widgets\OrdersChart;
use App\Filament\App\Widgets\RevenueChart;
use App\Filament\Pages\Dashboard as SuperAdminDashboard;
use App\Filament\Widgets\FinancialStatsOverview;
use Filament\Widgets\ChartWidget;

describe('RevenueChart Widget', function () {
    it('exists and is properly configured', function () {
        expect(class_exists(RevenueChart::class))->toBeTrue();
    });

    it('extends ChartWidget', function () {
        $reflection = new ReflectionClass(RevenueChart::class);

        expect($reflection->getParentClass()->getName())
            ->toBe(ChartWidget::class);
    });

    it('has correct chart heading', function () {
        $reflection = new ReflectionClass(RevenueChart::class);
        $heading = $reflection->getProperty('heading');

        expect($heading->getDefaultValue())->toBe('Ingresos verificados (ultimos 30 dias)');
    });

    it('is configured as line chart', function () {
        $widget = new RevenueChart;
        $method = new ReflectionMethod($widget, 'getType');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe('line');
    });

    it('has full column span', function () {
        $reflection = new ReflectionClass(RevenueChart::class);
        $columnSpan = $reflection->getProperty('columnSpan');

        expect($columnSpan->getDefaultValue())->toBe('full');
    });
});

describe('OrdersChart Widget', function () {
    it('exists and is properly configured', function () {
        expect(class_exists(OrdersChart::class))->toBeTrue();
    });

    it('extends ChartWidget', function () {
        $reflection = new ReflectionClass(OrdersChart::class);

        expect($reflection->getParentClass()->getName())
            ->toBe(ChartWidget::class);
    });

    it('has correct chart heading', function () {
        $reflection = new ReflectionClass(OrdersChart::class);
        $heading = $reflection->getProperty('heading');

        expect($heading->getDefaultValue())->toBe('Pedidos últimos 30 días');
    });

    it('is configured as a line chart for the time series', function () {
        $widget = new OrdersChart;
        $method = new ReflectionMethod($widget, 'getType');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe('line');
    });
});

describe('Widget Registration', function () {
    it('merchant dashboard registers the action-oriented operational cockpit', function () {
        $providerPath = dirname(__DIR__, 3).'/app/Providers/Filament/AppPanelProvider.php';
        $content = file_get_contents($providerPath);

        expect(class_exists(OperationalCockpitWidget::class))->toBeTrue()
            ->and($content)->toContain(OperationalCockpitWidget::class)
            ->and(view()->exists('filament.app.widgets.operational-cockpit-widget'))->toBeTrue();
    });

    it('custom dashboard page is available to the admin panel discovery path', function () {
        $providerPath = dirname(__DIR__, 3).'/app/Providers/Filament/AdminPanelProvider.php';
        $content = file_get_contents($providerPath);

        expect($content)
            ->toContain("discoverPages(in: app_path('Filament/Pages')")
            ->toContain('Dashboard::class');
    });

    it('admin dashboard registers financial launch widgets', function () {
        $providerPath = dirname(__DIR__, 3).'/app/Providers/Filament/AdminPanelProvider.php';
        $content = file_get_contents($providerPath);

        expect($content)->toContain(FinancialStatsOverview::class);
    });

    it('super admin dashboard exposes financial launch metrics', function () {
        $dashboard = new SuperAdminDashboard;
        $method = new ReflectionMethod($dashboard, 'getHeaderWidgets');
        $method->setAccessible(true);

        expect($method->invoke($dashboard))->toContain(FinancialStatsOverview::class);
    });
});
