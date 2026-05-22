<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Crm;

interface CrmDefaultPipelineGatewayInterface
{
    public function ensureForTenant(object $tenant): object;
}
