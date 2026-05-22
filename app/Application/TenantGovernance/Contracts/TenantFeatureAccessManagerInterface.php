<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Contracts;

use App\Domain\TenantGovernance\Enums\TenantFeature;

interface TenantFeatureAccessManagerInterface
{
    public function has(object $tenant, TenantFeature $feature): bool;
}
