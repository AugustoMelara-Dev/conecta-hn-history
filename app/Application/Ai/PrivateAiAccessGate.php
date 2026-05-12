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

        if (! $this->privateAgentEnabled()) {
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
        $allowlist = array_merge(
            (array) config('ai.private.tenant_allowlist', []),
            (array) config('conecta.features.tenant_private_agent_tenants', []),
        );

        $allowlist = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $allowlist,
        );

        if ($allowlist === []) {
            return false;
        }

        return in_array(strtolower((string) $tenant->id), $allowlist, true)
            || in_array(strtolower((string) $tenant->slug), $allowlist, true);
    }

    private function privateAgentEnabled(): bool
    {
        if ((bool) config('ai.private.enabled', false) === true) {
            return true;
        }

        return (array) config('conecta.features.tenant_private_agent_tenants', []) !== [];
    }
}
