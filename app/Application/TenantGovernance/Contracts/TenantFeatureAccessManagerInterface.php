<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\Tenant;

interface TenantFeatureAccessManagerInterface
{
    public function has(Tenant $tenant, TenantFeature $feature): bool;
}
