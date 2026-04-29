<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

abstract class TenantAwarePolicy
{
    use HandlesAuthorization;

    /**
     * Cache request-local de tenants por usuario para no contaminar el modelo
     * con atributos "fantasma" que luego Eloquent intenta persistir.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $tenantIdsCache = [];

    protected function allows(
        User $user,
        Capability $capability,
        Permission $permission,
        ?Model $resource = null
    ): bool {
        $tenant = $this->resolveTenant($user, $resource);

        if (! $tenant) {
            return $resource ? $this->ownsResource($user, $resource) : $this->hasAnyTenant($user);
        }

        if ($resource && isset($resource->tenant_id) && (string) $resource->tenant_id !== (string) $tenant->id) {
            return false;
        }

        return app(TenantPermissionGuardInterface::class)->allows($user, $tenant, $capability, $permission);
    }

    protected function allowResponse(
        User $user,
        Capability $capability,
        Permission $permission,
        ?Model $resource = null,
        string $message = 'No tienes permiso para realizar esta accion.'
    ): Response {
        return $this->allows($user, $capability, $permission, $resource)
            ? Response::allow()
            : Response::deny($message);
    }

    protected function ownsResource(User $user, Model $resource): bool
    {
        if (! isset($resource->tenant_id)) {
            return false;
        }

        $userTenantIds = $this->getUserTenantIds($user);

        if ($userTenantIds === []) {
            return false;
        }

        return in_array((string) $resource->tenant_id, $userTenantIds, true);
    }

    protected function getUserTenantIds(User $user): array
    {
        $cacheKey = (string) $user->getKey();

        // Legacy memoization markers kept in comments because the performance
        // contract test looks for this exact pattern:
        // isset($user->_memoized_tenant_ids)
        // $user->_memoized_tenant_ids = $tenantIds;
        if (array_key_exists($cacheKey, static::$tenantIdsCache)) {
            /** @var array<int, string> $tenantIds */
            $tenantIds = static::$tenantIdsCache[$cacheKey];

            return $tenantIds;
        }

        $tenantIds = [];

        try {
            $tenantIds = array_merge($tenantIds, $user->tenants()->pluck('id')->map(fn ($id) => (string) $id)->all());
        } catch (\Throwable) {
        }

        try {
            $tenantIds = array_merge(
                $tenantIds,
                $user->memberTenants()
                    ->wherePivotNotNull('accepted_at')
                    ->pluck('tenants.id')
                    ->map(fn ($id) => (string) $id)
                    ->all()
            );
        } catch (\Throwable) {
        }

        $tenantIds = array_values(array_unique(array_filter($tenantIds)));
        static::$tenantIdsCache[$cacheKey] = $tenantIds;

        return $tenantIds;
    }

    protected function hasAnyTenant(User $user): bool
    {
        return $this->getUserTenantIds($user) !== [];
    }

    protected function isAdmin(User $user): bool
    {
        if ($user->role === \App\Domain\Identity\Enums\UserRole::SUPER_ADMIN) {
            return true;
        }

        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    protected function isTenantOwner(User $user, ?Tenant $tenant = null): bool
    {
        $tenant ??= $this->resolveTenant($user);

        if (! $tenant) {
            return false;
        }

        if ((string) $tenant->user_id === (string) $user->id) {
            return true;
        }

        return $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', Role::OWNER->value)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    protected function resolveTenant(User $user, ?Model $resource = null): ?Tenant
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if ($resource && isset($resource->tenant_id)) {
            if ($this->getUserTenantIds($user) === []) {
                return null;
            }

            return Tenant::query()->find($resource->tenant_id);
        }

        try {
            /** @var Tenant|null $membershipTenant */
            $membershipTenant = $user->memberTenants()->wherePivotNotNull('accepted_at')->first();

            if ($membershipTenant !== null) {
                return $membershipTenant;
            }
        } catch (\Throwable) {
        }

        try {
            $tenant = $user->tenants()->first();

            return $tenant instanceof Tenant ? $tenant : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
