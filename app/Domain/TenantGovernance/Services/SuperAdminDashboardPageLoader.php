<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardPageLoader
{
    public function __construct(
        private readonly SuperAdminDashboardBoundStateReader $boundStateReader,
        private readonly SuperAdminDashboardPropsBinder $propsBinder,
    ) {}

    /**
     * @param  object  $target
     * @param  array<string, string>  $urls
     */
    public function load(object $target, array $urls): void
    {
        $this->propsBinder->bind($target, $this->boundStateReader->read($urls));
    }
}
