<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

interface TenantGrowthSignalsViewInterface
{
    /**
     * @return array<string, bool|int|float|string>
     */
    public function summarize(string $tenantId, string $businessType): array;
}
