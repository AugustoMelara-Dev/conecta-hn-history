<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Crm;

interface CrmPipelineSummaryGatewayInterface
{
    /**
     * @return array{pipeline: array<string, mixed>|null, totals: array<string, mixed>, stages: list<array<string, mixed>>, conversion: array<string, mixed>}
     */
    public function summarize(object $tenant, ?object $pipeline = null): array;
}
