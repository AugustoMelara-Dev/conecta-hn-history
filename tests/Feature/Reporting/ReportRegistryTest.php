<?php

declare(strict_types=1);

use App\Application\Reporting\ReportRegistry;

it('ReportRegistry has all 25 reports registered', function () {
    $registry = new ReportRegistry();

    // Register all reports
    $reports = [
        new \App\Application\Reporting\Reports\RevenueByMonthReport(),
        new \App\Application\Reporting\Reports\MrrArrReport(),
        new \App\Application\Reporting\Reports\AvgOrderValueReport(),
        new \App\Application\Reporting\Reports\TopProductsReport(),
        new \App\Application\Reporting\Reports\CustomerLtvReport(),
        new \App\Application\Reporting\Reports\ChurnRateReport(),
        new \App\Application\Reporting\Reports\CampaignRoiReport(),
        new \App\Application\Reporting\Reports\ConversionFunnelReport(),
        new \App\Application\Reporting\Reports\DealVelocityReport(),
        new \App\Application\Reporting\Reports\WinRateReport(),
        new \App\Application\Reporting\Reports\LeadScoreReport(),
        new \App\Application\Reporting\Reports\ActivityVolumeReport(),
        new \App\Application\Reporting\Reports\CohortRetentionReport(),
        new \App\Application\Reporting\Reports\AttributionBySourceReport(),
        new \App\Application\Reporting\Reports\ChannelPerformanceReport(),
        new \App\Application\Reporting\Reports\NpsScoreReport(),
        new \App\Application\Reporting\Reports\SlowMoversReport(),
        new \App\Application\Reporting\Reports\InventoryTurnoverReport(),
        new \App\Application\Reporting\Reports\FulfillmentSlaReport(),
        new \App\Application\Reporting\Reports\GeographicDistributionReport(),
        new \App\Application\Reporting\Reports\EmployeePerformanceReport(),
        new \App\Application\Reporting\Reports\ProductMarginReport(),
        new \App\Application\Reporting\Reports\DiscountEffectivenessReport(),
        new \App\Application\Reporting\Reports\RefundRateReport(),
        new \App\Application\Reporting\Reports\CustomerSegmentsReport(),
    ];

    foreach ($reports as $r) {
        $registry->register($r);
    }

    expect(count($registry->all()))->toBe(25);
});

it('Every ReportKey has a registered Report class', function () {
    $registry = new ReportRegistry();
    foreach ([
        \App\Application\Reporting\Reports\RevenueByMonthReport::class,
        \App\Application\Reporting\Reports\MrrArrReport::class,
        \App\Application\Reporting\Reports\AvgOrderValueReport::class,
        \App\Application\Reporting\Reports\TopProductsReport::class,
        \App\Application\Reporting\Reports\CustomerLtvReport::class,
        \App\Application\Reporting\Reports\ChurnRateReport::class,
        \App\Application\Reporting\Reports\CampaignRoiReport::class,
        \App\Application\Reporting\Reports\ConversionFunnelReport::class,
        \App\Application\Reporting\Reports\DealVelocityReport::class,
        \App\Application\Reporting\Reports\WinRateReport::class,
        \App\Application\Reporting\Reports\LeadScoreReport::class,
        \App\Application\Reporting\Reports\ActivityVolumeReport::class,
        \App\Application\Reporting\Reports\CohortRetentionReport::class,
        \App\Application\Reporting\Reports\AttributionBySourceReport::class,
        \App\Application\Reporting\Reports\ChannelPerformanceReport::class,
        \App\Application\Reporting\Reports\NpsScoreReport::class,
        \App\Application\Reporting\Reports\SlowMoversReport::class,
        \App\Application\Reporting\Reports\InventoryTurnoverReport::class,
        \App\Application\Reporting\Reports\FulfillmentSlaReport::class,
        \App\Application\Reporting\Reports\GeographicDistributionReport::class,
        \App\Application\Reporting\Reports\EmployeePerformanceReport::class,
        \App\Application\Reporting\Reports\ProductMarginReport::class,
        \App\Application\Reporting\Reports\DiscountEffectivenessReport::class,
        \App\Application\Reporting\Reports\RefundRateReport::class,
        \App\Application\Reporting\Reports\CustomerSegmentsReport::class,
    ] as $reportClass) {
        $instance = new $reportClass();
        $registry->register($instance);
        expect($registry->has($instance->getKey()))->toBeTrue();
    }

    // Check that every ReportKey enum has a registered Report
    foreach (\App\Domain\Reporting\Enums\ReportKey::cases() as $key) {
        expect($registry->has($key))->toBeTrue("Missing report for {$key->value}");
    }
});

it('ReportRegistry groups by category', function () {
    $registry = new ReportRegistry();
    $registry->register(new \App\Application\Reporting\Reports\RevenueByMonthReport());
    $registry->register(new \App\Application\Reporting\Reports\ConversionFunnelReport());

    $grouped = $registry->grouped();
    expect($grouped)->toHaveKey('Ingresos');
    expect($grouped)->toHaveKey('Ventas');
});
