<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\CustomerProfiles\Commands\MergeCustomerProfilesCommand;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\CustomerProfile;

final readonly class MergeCustomerProfilesHandler
{
    public function __construct(
        private CustomerProfileRepositoryInterface $profiles,
        private CustomerProfileManager $manager,
    ) {}

    public function handle(MergeCustomerProfilesCommand $command): ?CustomerProfile
    {
        $primary = $this->profiles->findTenantRecord($command->tenantId, $command->primaryProfileId);
        $duplicate = $this->profiles->findTenantRecord($command->tenantId, $command->duplicateProfileId);

        if ($primary === null || $duplicate === null) {
            return null;
        }

        return $this->manager->mergeProfiles($primary, $duplicate, $command->actorUserId);
    }
}
