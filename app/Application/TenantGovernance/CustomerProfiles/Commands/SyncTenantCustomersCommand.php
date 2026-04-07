<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class SyncTenantCustomersCommand
{
    public function __construct(
        public string $tenantId,
    ) {}
}
