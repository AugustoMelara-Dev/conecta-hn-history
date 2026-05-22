<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Domain\TenantGovernance\Enums\Capability;

interface CapabilityManagerInterface
{
    public function has(object $tenant, Capability $capability): bool;

    public function hasForTenantId(string $tenantId, Capability $capability): bool;

    /**
     * @return array<int, Capability>
     */
    public function allForTenant(object $tenant): array;
}
