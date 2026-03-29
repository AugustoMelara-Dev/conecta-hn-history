<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class RedeemCustomerProfileRewardCommand
{
    public function __construct(
        public string $tenantId,
        public int $profileId,
        public int $rewardId,
        public ?string $actorUserId,
    ) {}
}
