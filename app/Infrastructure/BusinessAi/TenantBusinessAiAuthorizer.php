<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Application\BusinessAi\Contracts\BusinessAiAuthorizerInterface;
use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;

final class TenantBusinessAiAuthorizer implements BusinessAiAuthorizerInterface
{
    public function __construct(private readonly TenantPermissionGuardInterface $guard) {}

    public function authorize(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->guard->allows($user, $tenant, Capability::AI_CONTENT, Permission::AI_USE);
    }
}
