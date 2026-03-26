<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardBootstrapper
{
    public function __construct(
        private readonly TenantCrmPortfolioManager $portfolioManager,
        private readonly TenantJourneyOpsMonitor $journeyOpsMonitor,
        private readonly SuperAdminDashboardPageLoader $pageLoader,
        private readonly SuperAdminDashboardUrlMapReader $urlMapReader,
    ) {}

    /**
     * @param  object  $target
     */
    public function boot(object $target): void
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

        $this->pageLoader->load($target, $this->urlMapReader->read());
    }
}
