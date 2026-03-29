<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Models\CustomerTask;

interface TenantCustomerTaskFinderInterface
{
    public function findTask(string $tenantId, int $taskId): ?CustomerTask;
}
