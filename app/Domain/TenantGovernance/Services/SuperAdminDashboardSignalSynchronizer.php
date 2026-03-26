<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardSignalSynchronizer
{
    public function __construct(
        private readonly TenantCrmPortfolioManager $portfolioManager,
        private readonly TenantJourneyOpsMonitor $journeyOpsMonitor,
    ) {}

    public function sync(): void
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
    }
}
