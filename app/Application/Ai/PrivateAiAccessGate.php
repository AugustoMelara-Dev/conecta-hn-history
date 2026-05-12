<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;

class PrivateAiAccessGate
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
    ) {}

    public function allows(?User $user, int|string $tenantId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ((bool) config('ai.private.enabled', false) !== true) {
            return false;
        }

        $tenant = Tenant::withoutGlobalScopes()->find((string) $tenantId);

        if (! $tenant instanceof Tenant || ! $this->tenantIsAllowlisted($tenant)) {
            return false;
        }

        return $this->permissionGuard->allows(
            $user,
            $tenant,
            Capability::AI_CONTENT,
            Permission::AI_USE,
        );
    }

    private function tenantIsAllowlisted(Tenant $tenant): bool
    {
        $allowlist = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('ai.private.tenant_allowlist', []),
        );

        if ($allowlist === []) {
            return false;
        }

        return in_array(strtolower((string) $tenant->id), $allowlist, true)
            || in_array(strtolower((string) $tenant->slug), $allowlist, true);
    }
}
