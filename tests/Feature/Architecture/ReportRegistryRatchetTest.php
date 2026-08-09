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

/**
 * PR5 Architecture Ratchet — Report Registry coverage.
 *
 * Cada ReportKey enum case debe tener un Report class registrado.
 */
it('Every ReportKey has a corresponding Report class registered', function () {
    $registry = new ReportRegistry;

    // Register all 25 reports
    $reportClasses = [
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
    ];

    foreach ($reportClasses as $reportClass) {
        $instance = new $reportClass;
        $registry->register($instance);
    }

    foreach (ReportKey::cases() as $key) {
        expect($registry->has($key))->toBeTrue("Missing report for {$key->value}");
    }
});
