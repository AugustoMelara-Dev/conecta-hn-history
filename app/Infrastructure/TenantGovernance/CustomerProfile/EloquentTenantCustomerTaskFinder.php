<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Application\TenantGovernance\Contracts\TenantCustomerTaskFinderInterface;
use App\Models\CustomerTask;

final class EloquentTenantCustomerTaskFinder implements TenantCustomerTaskFinderInterface
{
    public function findTask(string $tenantId, int $taskId): ?CustomerTask
    {
        return CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($taskId);
    }
}
