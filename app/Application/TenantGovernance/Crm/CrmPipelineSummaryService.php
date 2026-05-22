<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Crm;

final readonly class CrmPipelineSummaryService
{
    public function __construct(
        private CrmPipelineSummaryGatewayInterface $gateway,
    ) {}

    /**
     * @return array{pipeline: array<string, mixed>|null, totals: array<string, mixed>, stages: list<array<string, mixed>>, conversion: array<string, mixed>}
     */
    public function summarize(object $tenant, ?object $pipeline = null): array
    {
        return $this->gateway->summarize($tenant, $pipeline);
    }
}
