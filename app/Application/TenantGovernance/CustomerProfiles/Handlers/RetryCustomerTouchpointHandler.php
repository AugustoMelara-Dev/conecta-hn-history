<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerTouchpointFinderInterface;
use App\Application\TenantGovernance\CustomerProfiles\Commands\RetryCustomerTouchpointCommand;
use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Models\CustomerTouchpoint;

final readonly class RetryCustomerTouchpointHandler
{
    public function __construct(
        private TenantCustomerTouchpointFinderInterface $touchpoints,
        private CustomerAutomationManager $automation,
    ) {}

    public function handle(RetryCustomerTouchpointCommand $command): ?CustomerTouchpoint
    {
        $touchpoint = $this->touchpoints->findTouchpoint($command->tenantId, $command->touchpointId);

        if (! $touchpoint instanceof CustomerTouchpoint) {
            return null;
        }

        return $this->automation->retryTouchpoint($touchpoint, $command->actorUserId);
    }
}
