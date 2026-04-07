<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Queries;

final readonly class GetTenantOperationalComparisonsQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
