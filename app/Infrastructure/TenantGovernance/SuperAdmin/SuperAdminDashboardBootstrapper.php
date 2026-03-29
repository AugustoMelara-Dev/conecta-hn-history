<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

final class SuperAdminDashboardBootstrapper
{
    public function __construct(
        private readonly SuperAdminDashboardSignalSynchronizer $signalSynchronizer,
        private readonly SuperAdminDashboardPageLoader $pageLoader,
        private readonly SuperAdminDashboardUrlMapReader $urlMapReader,
    ) {}

    public function boot(object $target): void
    {
        $this->signalSynchronizer->sync();
        $this->pageLoader->load($target);
    }
}
