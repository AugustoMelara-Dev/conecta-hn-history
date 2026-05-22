<?php

declare(strict_types=1);

namespace App\Application\Access\Contracts;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;

interface TenantPermissionGuardInterface
{
    public function allows(object $user, object $tenant, Capability $capability, Permission $permission): bool;
}
