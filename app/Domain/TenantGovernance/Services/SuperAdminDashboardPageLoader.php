<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardPageLoader
{
    public function __construct(
        private readonly SuperAdminDashboardPageStateReader $pageStateReader,
        private readonly SuperAdminDashboardPropsBinder $propsBinder,
    ) {}

    /**
     * @param  object  $target
     */
    public function load(object $target): void
    {
        $this->propsBinder->bind($target, $this->pageStateReader->read());
    }
}
