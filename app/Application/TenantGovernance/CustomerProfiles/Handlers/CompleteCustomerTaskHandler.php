<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Handlers;

use App\Application\TenantGovernance\Contracts\TenantCustomerTaskFinderInterface;
use App\Application\TenantGovernance\CustomerProfiles\Commands\CompleteCustomerTaskCommand;
use App\Domain\TenantGovernance\Services\CustomerTaskManager;
use App\Models\CustomerTask;

final readonly class CompleteCustomerTaskHandler
{
    public function __construct(
        private TenantCustomerTaskFinderInterface $tasks,
        private CustomerTaskManager $manager,
    ) {}

    public function handle(CompleteCustomerTaskCommand $command): ?CustomerTask
    {
        $task = $this->tasks->findTask($command->tenantId, $command->taskId);

        if (! $task instanceof CustomerTask) {
            return null;
        }

        return $this->manager->complete($task, $command->actorUserId);
    }
}
