<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class AwardCustomerProfileBonusCommand
{
    public function __construct(
        public string $tenantId,
        public int $profileId,
        public int $points,
        public string $description,
        public ?string $actorUserId,
    ) {}
}
