<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Crm\CanonicalCrmPermissionGate;
use App\Models\CrmActivity;
use App\Models\Tenant;
use App\Models\User;

class ActivityPolicy
{
    public function __construct(private readonly CanonicalCrmPermissionGate $permissionGate) {}

    public function viewAny(User $user): bool
    {
        return $this->authorized($user, 'crm.activities.view');
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $this->authorized($user, 'crm.activities.view')
            && $this->belongsToActiveTenant($activity);
    }

    public function create(User $user): bool
    {
        return $this->authorized($user, 'crm.activities.create');
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $this->authorized($user, 'crm.activities.update')
            && $this->belongsToActiveTenant($activity);
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $this->authorized($user, 'crm.activities.delete')
            && $this->belongsToActiveTenant($activity);
    }

    private function authorized(User $user, string $permission): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && $this->permissionGate->allows($user, $tenant, $permission);
    }

    private function belongsToActiveTenant(CrmActivity $activity): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && (string) $activity->tenant_id === (string) $tenant->getKey();
    }

    private function currentTenant(): ?Tenant
    {
        if (! app()->bound('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        return $tenant instanceof Tenant && (bool) $tenant->is_active ? $tenant : null;
    }
}
