<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\CustomerProfiles\Commands\SyncTenantCustomersCommand;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;

final readonly class SyncTenantCustomersHandler
{
    public function __construct(
        private CustomerProfileManager $profiles,
    ) {}

    public function handle(SyncTenantCustomersCommand $command): void
    {
        $this->profiles->syncTenant($command->tenantId);
    }
}
