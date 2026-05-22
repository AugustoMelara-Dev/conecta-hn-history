<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Crm;

final readonly class CrmDefaultPipelineService
{
    public function __construct(
        private CrmDefaultPipelineGatewayInterface $gateway,
    ) {}

    public function ensureForTenant(object $tenant): object
    {
        return $this->gateway->ensureForTenant($tenant);
    }
}
