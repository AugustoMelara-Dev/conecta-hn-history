<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardPageStateReader
{
    public function __construct(
        private readonly SuperAdminDashboardUrlMapReader $urlMapReader,
        private readonly SuperAdminDashboardBoundStateReader $boundStateReader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->boundStateReader->read($this->urlMapReader->read());
    }
}
