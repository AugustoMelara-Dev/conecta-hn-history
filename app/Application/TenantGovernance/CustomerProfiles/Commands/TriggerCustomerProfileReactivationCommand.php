<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class TriggerCustomerProfileReactivationCommand
{
    public function __construct(
        public string $tenantId,
        public int $profileId,
        public ?string $actorUserId,
    ) {}
}
