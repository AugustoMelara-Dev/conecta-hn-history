<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

interface TenantOperationalComparisonsViewInterface
{
    /**
     * @return array{
     *   ownerComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>,
     *   locationComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>
     * }
     */
    public function summarize(string $tenantId): array;
}
