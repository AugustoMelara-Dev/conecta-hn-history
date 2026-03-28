<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Queries;

final readonly class GetTenantJourneyAnalyticsQuery
{
    public function __construct(
        public string $tenantId,
    ) {}
}
