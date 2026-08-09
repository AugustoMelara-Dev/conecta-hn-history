<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Crm\CanonicalCrmPermissionGate;
use App\Models\CrmCompany;
use App\Models\Tenant;
use App\Models\User;

class CompanyPolicy
{
    public function __construct(private readonly CanonicalCrmPermissionGate $permissionGate) {}

    public function viewAny(User $user): bool
    {
        return $this->authorized($user, 'crm.companies.view');
    }

    public function view(User $user, CrmCompany $company): bool
    {
        return $this->authorized($user, 'crm.companies.view')
            && $this->belongsToActiveTenant($company);
    }

    public function create(User $user): bool
    {
        return $this->authorized($user, 'crm.companies.create');
    }

    public function update(User $user, CrmCompany $company): bool
    {
        return $this->authorized($user, 'crm.companies.update')
            && $this->belongsToActiveTenant($company);
    }

    public function delete(User $user, CrmCompany $company): bool
    {
        return $this->authorized($user, 'crm.companies.delete')
            && $this->belongsToActiveTenant($company);
    }

    private function authorized(User $user, string $permission): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && $this->permissionGate->allows($user, $tenant, $permission);
    }

    private function belongsToActiveTenant(CrmCompany $company): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && (string) $company->tenant_id === (string) $tenant->getKey();
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
