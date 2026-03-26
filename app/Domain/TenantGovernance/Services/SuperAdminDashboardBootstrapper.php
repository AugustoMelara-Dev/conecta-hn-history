<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardBootstrapper
{
    public function __construct(
        private readonly TenantCrmPortfolioManager $portfolioManager,
        private readonly TenantJourneyOpsMonitor $journeyOpsMonitor,
        private readonly SuperAdminDashboardPageLoader $pageLoader,
    ) {}

    /**
     * @param  object  $target
     * @param  array<string, string>  $urls
     */
    public function boot(object $target, array $urls, callable $safeRoute): void
    {
        try {
            $this->portfolioManager->syncSignals();
        } catch (\Throwable) {
            // Evita que una sincronizacion CRM incompleta tumbe el dashboard del superadmin.
        }

        try {
            $this->journeyOpsMonitor->syncSignals();
        } catch (\Throwable) {
            // Evita que la lectura de journeys CRM tumbe el dashboard.
        }

        $this->pageLoader->load($target, $urls, $safeRoute);
    }
}
