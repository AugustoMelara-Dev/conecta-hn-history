<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class RunCustomerReactivationSweepCommand
{
    public function __construct(
        public string $tenantId,
        public ?string $actorUserId,
        public int $limit = 25,
    ) {}
}
