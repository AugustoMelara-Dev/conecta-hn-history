<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardPageLoader
{
    public function __construct(
        private readonly SuperAdminDashboardViewStateReader $viewStateReader,
        private readonly SuperAdminDashboardPropsBinder $propsBinder,
    ) {}

    /**
     * @param  object  $target
     * @param  array<string, string>  $urls
     */
    public function load(object $target, array $urls, callable $safeRoute): void
    {
        try {
            $state = $this->viewStateReader->read($urls, $safeRoute);
        } catch (\Throwable) {
            $state = $this->viewStateReader->defaults();
        }

        $this->propsBinder->bind($target, $state, $this->viewStateReader->defaults());
    }
}
