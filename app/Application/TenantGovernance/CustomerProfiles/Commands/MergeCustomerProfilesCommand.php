<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class MergeCustomerProfilesCommand
{
    public function __construct(
        public string $tenantId,
        public int $primaryProfileId,
        public int $duplicateProfileId,
        public ?string $actorUserId,
    ) {}
}
