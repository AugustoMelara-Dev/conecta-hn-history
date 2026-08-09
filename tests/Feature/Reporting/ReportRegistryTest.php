<?php

declare(strict_types=1);

use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\Reports\ActivityVolumeReport;
use App\Application\Reporting\Reports\AttributionBySourceReport;
use App\Application\Reporting\Reports\AvgOrderValueReport;
use App\Application\Reporting\Reports\CampaignRoiReport;
use App\Application\Reporting\Reports\ChannelPerformanceReport;
use App\Application\Reporting\Reports\ChurnRateReport;
use App\Application\Reporting\Reports\CohortRetentionReport;
use App\Application\Reporting\Reports\ConversionFunnelReport;
use App\Application\Reporting\Reports\CustomerLtvReport;
use App\Application\Reporting\Reports\CustomerSegmentsReport;
use App\Application\Reporting\Reports\DealVelocityReport;
use App\Application\Reporting\Reports\DiscountEffectivenessReport;
use App\Application\Reporting\Reports\EmployeePerformanceReport;
use App\Application\Reporting\Reports\FulfillmentSlaReport;
use App\Application\Reporting\Reports\GeographicDistributionReport;
use App\Application\Reporting\Reports\InventoryTurnoverReport;
use App\Application\Reporting\Reports\LeadScoreReport;
use App\Application\Reporting\Reports\MrrArrReport;
use App\Application\Reporting\Reports\NpsScoreReport;
use App\Application\Reporting\Reports\ProductMarginReport;
use App\Application\Reporting\Reports\RefundRateReport;
use App\Application\Reporting\Reports\RevenueByMonthReport;
use App\Application\Reporting\Reports\SlowMoversReport;
use App\Application\Reporting\Reports\TopProductsReport;
use App\Application\Reporting\Reports\WinRateReport;
use App\Domain\Reporting\Enums\ReportKey;

it('ReportRegistry has all 25 reports registered', function () {
    $registry = new ReportRegistry;

    // Register all reports
    $reports = [
        new RevenueByMonthReport,
        new MrrArrReport,
        new AvgOrderValueReport,
        new TopProductsReport,
        new CustomerLtvReport,
        new ChurnRateReport,
        new CampaignRoiReport,
        new ConversionFunnelReport,
        new DealVelocityReport,
        new WinRateReport,
        new LeadScoreReport,
        new ActivityVolumeReport,
        new CohortRetentionReport,
        new AttributionBySourceReport,
        new ChannelPerformanceReport,
        new NpsScoreReport,
        new SlowMoversReport,
        new InventoryTurnoverReport,
        new FulfillmentSlaReport,
        new GeographicDistributionReport,
        new EmployeePerformanceReport,
        new ProductMarginReport,
        new DiscountEffectivenessReport,
        new RefundRateReport,
        new CustomerSegmentsReport,
    ];

    foreach ($reports as $r) {
        $registry->register($r);
    }

    expect(count($registry->all()))->toBe(25);
});

it('Every ReportKey has a registered Report class', function () {
    $registry = new ReportRegistry;
    foreach ([
        RevenueByMonthReport::class,
        MrrArrReport::class,
        AvgOrderValueReport::class,
        TopProductsReport::class,
        CustomerLtvReport::class,
        ChurnRateReport::class,
        CampaignRoiReport::class,
        ConversionFunnelReport::class,
        DealVelocityReport::class,
        WinRateReport::class,
        LeadScoreReport::class,
        ActivityVolumeReport::class,
        CohortRetentionReport::class,
        AttributionBySourceReport::class,
        ChannelPerformanceReport::class,
        NpsScoreReport::class,
        SlowMoversReport::class,
        InventoryTurnoverReport::class,
        FulfillmentSlaReport::class,
        GeographicDistributionReport::class,
        EmployeePerformanceReport::class,
        ProductMarginReport::class,
        DiscountEffectivenessReport::class,
        RefundRateReport::class,
        CustomerSegmentsReport::class,
    ] as $reportClass) {
        $instance = new $reportClass;
        $registry->register($instance);
        expect($registry->has($instance->getKey()))->toBeTrue();
    }

    // Check that every ReportKey enum has a registered Report
    foreach (ReportKey::cases() as $key) {
        expect($registry->has($key))->toBeTrue("Missing report for {$key->value}");
    }
});

it('ReportRegistry groups by category', function () {
    $registry = new ReportRegistry;
    $registry->register(new RevenueByMonthReport);
    $registry->register(new ConversionFunnelReport);

    $grouped = $registry->grouped();
    expect($grouped)->toHaveKey('Ingresos');
    expect($grouped)->toHaveKey('Ventas');
});
