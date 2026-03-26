<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardBoundStateReader
{
    public function __construct(
        private readonly SuperAdminDashboardViewStateReader $viewStateReader,
    ) {}

    /**
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(array $urls): array
    {
        try {
            return $this->viewStateReader->read($urls);
        } catch (\Throwable) {
            return $this->viewStateReader->defaults();
        }
    }
}
