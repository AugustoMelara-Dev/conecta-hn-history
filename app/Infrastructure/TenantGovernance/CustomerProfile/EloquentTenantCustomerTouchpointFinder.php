<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Application\TenantGovernance\Contracts\TenantCustomerTouchpointFinderInterface;
use App\Models\CustomerTouchpoint;

final class EloquentTenantCustomerTouchpointFinder implements TenantCustomerTouchpointFinderInterface
{
    public function findTouchpoint(string $tenantId, int $touchpointId): ?CustomerTouchpoint
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($touchpointId);
    }
}
