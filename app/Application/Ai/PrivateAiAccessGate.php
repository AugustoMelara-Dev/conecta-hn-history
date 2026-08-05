<?php

declare(strict_types=1);

namespace App\Application\Ai;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;

class PrivateAiAccessGate
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private PrivateAiTenantAccessReaderInterface $tenantReader,
    ) {}

    public function allows(?object $user, string $tenantId): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (! $this->privateAgentEnabled()) {
            return false;
        }

        $tenant = $this->tenantReader->findAllowlistedTenant($tenantId);

        if (! is_object($tenant)) {
            return false;
        }

        return $this->permissionGuard->allows(
            $user,
            $tenant,
            Capability::AI_CONTENT,
            Permission::AI_USE,
        );
    }

    private function privateAgentEnabled(): bool
    {
        if ((bool) config('ai.private.enabled', false) === true) {
            return true;
        }

        return (array) config('conecta.features.tenant_private_agent_tenants', []) !== [];
    }
}
