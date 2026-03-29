<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\CustomerProfiles\Commands\RunCustomerReactivationSweepCommand;
use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;

final readonly class RunCustomerReactivationSweepHandler
{
    public function __construct(
        private CustomerLifecycleTriggerManager $lifecycle,
    ) {}

    /**
     * @return array{processed:int,sent:int,skipped:int,failed:int}
     */
    public function handle(RunCustomerReactivationSweepCommand $command): array
    {
        return $this->lifecycle->runReactivationSweep(
            tenant: $command->tenantId,
            actorUserId: $command->actorUserId,
            limit: $command->limit,
        );
    }
}
