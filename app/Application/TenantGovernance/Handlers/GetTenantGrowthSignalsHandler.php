<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Handlers;

use App\Application\TenantGovernance\Contracts\TenantGrowthSignalsViewInterface;
use App\Application\TenantGovernance\Queries\GetTenantGrowthSignalsQuery;

final readonly class GetTenantGrowthSignalsHandler
{
    public function __construct(
        private TenantGrowthSignalsViewInterface $view,
    ) {}

    /**
     * @return array<string, bool|int|float|string>
     */
    public function handle(GetTenantGrowthSignalsQuery $query): array
    {
        return $this->view->summarize(
            tenantId: $query->tenantId,
            businessType: $query->businessType,
        );
    }
}
