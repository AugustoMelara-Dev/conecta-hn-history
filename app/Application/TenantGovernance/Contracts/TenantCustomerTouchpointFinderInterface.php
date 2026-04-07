<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Models\CustomerTouchpoint;

interface TenantCustomerTouchpointFinderInterface
{
    public function findTouchpoint(string $tenantId, int $touchpointId): ?CustomerTouchpoint;
}
