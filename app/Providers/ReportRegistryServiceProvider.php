<?php

declare(strict_types=1);

namespace App\Providers;

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
use Illuminate\Support\ServiceProvider;

/**
 * ReportRegistryServiceProvider - Registra los 25 Report classes en el ReportRegistry.
 *
 * Auto-discovery: itera todos los Report classes en app/Application/Reporting/Reports.
 */
class ReportRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportRegistry::class, function () {
            $registry = new ReportRegistry;

            // Manually register each report
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

            foreach ($reports as $report) {
                $registry->register($report);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        // Nothing for now
    }
}
