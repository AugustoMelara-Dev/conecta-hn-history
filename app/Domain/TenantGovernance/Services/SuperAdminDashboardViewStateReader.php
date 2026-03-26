<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardViewStateReader
{
    public function __construct(
        private readonly SuperAdminDashboardDefaultsReader $defaultsReader,
        private readonly SuperAdminDashboardScalarStateReader $scalarStateReader,
        private readonly SuperAdminDashboardStateReader $stateReader,
        private readonly SuperAdminDashboardSnapshotReader $snapshotReader,
    ) {}

    /**
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(array $urls, callable $safeRoute): array
    {
        $state = $this->stateReader->read($urls, $safeRoute);

        return array_replace(
            $this->defaults(),
            $this->scalarStateReader->read($state),
            $this->snapshotReader->read($state),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->defaultsReader->read();
    }
}
