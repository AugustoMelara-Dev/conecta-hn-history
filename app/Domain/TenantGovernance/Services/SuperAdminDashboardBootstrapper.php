<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardBootstrapper
{
    public function __construct(
        private readonly SuperAdminDashboardSignalSynchronizer $signalSynchronizer,
        private readonly SuperAdminDashboardPageLoader $pageLoader,
        private readonly SuperAdminDashboardUrlMapReader $urlMapReader,
    ) {}

    /**
     * @param  object  $target
     */
    public function boot(object $target): void
    {
        $this->signalSynchronizer->sync();
        $this->pageLoader->load($target);
    }
}
