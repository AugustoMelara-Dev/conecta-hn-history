<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Handlers;

use App\Application\TenantGovernance\Contracts\TenantOperationalComparisonsViewInterface;
use App\Application\TenantGovernance\Queries\GetTenantOperationalComparisonsQuery;

final readonly class GetTenantOperationalComparisonsHandler
{
    public function __construct(
        private TenantOperationalComparisonsViewInterface $view,
    ) {}

    /**
     * @return array{
     *   ownerComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>,
     *   locationComparisons:list<array{name:string,revenue:string,customers:int,openTasks:int,overdueTasks:int,repeatRate:string,context:string}>
     * }
     */
    public function handle(GetTenantOperationalComparisonsQuery $query): array
    {
        return $this->view->summarize($query->tenantId);
    }
}
