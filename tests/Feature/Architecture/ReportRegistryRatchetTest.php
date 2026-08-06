<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * PR5 Architecture Ratchet — Report Registry coverage.
 *
 * Cada ReportKey enum case debe tener un Report class registrado.
 */

it('Every ReportKey has a corresponding Report class registered', function () {
    $registry = new \App\Application\Reporting\ReportRegistry();

    // Register all 25 reports
    $reportClasses = [
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
    ];

    foreach ($reportClasses as $reportClass) {
        $instance = new $reportClass();
        $registry->register($instance);
    }

    foreach (\App\Domain\Reporting\Enums\ReportKey::cases() as $key) {
        expect($registry->has($key))->toBeTrue("Missing report for {$key->value}");
    }
});
