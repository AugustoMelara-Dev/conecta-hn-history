<?php

declare(strict_types=1);

use App\Filament\App\Widgets\OrdersChart;
use App\Filament\App\Widgets\RevenueChart;

describe('RevenueChart Widget', function () {
    it('exists and is properly configured', function () {
        expect(class_exists(RevenueChart::class))->toBeTrue();
    });

    it('extends ChartWidget', function () {
        $reflection = new ReflectionClass(RevenueChart::class);

        expect($reflection->getParentClass()->getName())
            ->toBe(\Filament\Widgets\ChartWidget::class);
    });

    it('has correct chart heading', function () {
        $reflection = new ReflectionClass(RevenueChart::class);
        $heading = $reflection->getProperty('heading');

        expect($heading->getDefaultValue())->toBe('Ingresos por Ordenes (Ultimos 30 dias)');
    });

    it('is configured as line chart', function () {
        $widget = new RevenueChart();
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
            ->toBe(\Filament\Widgets\ChartWidget::class);
    });

    it('has correct chart heading', function () {
        $reflection = new ReflectionClass(OrdersChart::class);
        $heading = $reflection->getProperty('heading');

        expect($heading->getDefaultValue())->toBe('Ordenes por Dia (Ultima Semana)');
    });

    it('is configured as bar chart', function () {
        $widget = new OrdersChart();
        $method = new ReflectionMethod($widget, 'getType');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe('bar');
    });
});

describe('Widget Registration', function () {
    it('RevenueChart is registered in AppPanelProvider', function () {
        $providerPath = app_path('Providers/Filament/AppPanelProvider.php');
        $content = file_get_contents($providerPath);

        expect($content)->toContain('RevenueChart::class');
    });

    it('OrdersChart is registered in AppPanelProvider', function () {
        $providerPath = app_path('Providers/Filament/AppPanelProvider.php');
        $content = file_get_contents($providerPath);

        expect($content)->toContain('OrdersChart::class');
    });
});
