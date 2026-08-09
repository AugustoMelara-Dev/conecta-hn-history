<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical permission lookup shared by the CRM writer and policy perimeter.
 */
final readonly class CanonicalCrmPermissionGate
{
    public function allows(User $actor, Tenant $tenant, string $permission): bool
    {
        if (! $tenant->exists || ! (bool) $tenant->is_active) {
            return false;
        }

        if ((string) $tenant->user_id === (string) $actor->getKey()) {
            return true;
        }

        if (! $actor->canAccessTenant($tenant)) {
            return false;
        }

        if (! Schema::hasTable('permissions')
            || ! Schema::hasTable('model_has_permissions')
            || ! Schema::hasTable('model_has_roles')
            || ! Schema::hasTable('role_has_permissions')) {
            return false;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $permission)
            ->where('guard_name', 'web')
            ->value('id');
        if ($permissionId === null) {
            return false;
        }

        if (DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->where('model_type', $actor->getMorphClass())
            ->where('model_id', $actor->getKey())
            ->exists()) {
            return true;
        }

        return DB::table('model_has_roles')
            ->where('model_type', $actor->getMorphClass())
            ->where('model_id', $actor->getKey())
            ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->where('role_has_permissions.permission_id', $permissionId)
            ->exists();
    }
}
