<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;

interface CapabilityManagerInterface
{
    public function has(Tenant $tenant, Capability $capability): bool;

    /**
     * @return array<int, Capability>
     */
    public function allForTenant(Tenant $tenant): array;
}
