<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\CustomerProfiles\Commands\AwardCustomerProfileBonusCommand;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Domain\TenantGovernance\Services\TenantCustomerProfileActionCoordinator;

final readonly class AwardCustomerProfileBonusHandler
{
    public function __construct(
        private CustomerProfileRepositoryInterface $profiles,
        private TenantCustomerProfileActionCoordinator $actions,
    ) {}

    /**
     * @return array{title:string,color:string,body:string}|null
     */
    public function handle(AwardCustomerProfileBonusCommand $command): ?array
    {
        $profile = $this->profiles->findTenantRecord($command->tenantId, $command->profileId);

        if ($profile === null) {
            return null;
        }

        return $this->actions->awardBonus($profile, [
            'points' => $command->points,
            'description' => $command->description,
        ], $command->actorUserId);
    }
}
