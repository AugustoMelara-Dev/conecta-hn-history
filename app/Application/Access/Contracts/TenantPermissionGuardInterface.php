<?php

declare(strict_types=1);

namespace App\Application\Access\Contracts;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;

interface TenantPermissionGuardInterface
{
    public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool;
}
