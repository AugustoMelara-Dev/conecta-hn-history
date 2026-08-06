<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrmCompany;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.companies.view');
    }

    public function view(User $user, CrmCompany $company): bool
    {
        return $user->tenant_id === $company->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm.companies.create');
    }

    public function update(User $user, CrmCompany $company): bool
    {
        return $user->tenant_id === $company->tenant_id;
    }

    public function delete(User $user, CrmCompany $company): bool
    {
        return $user->can('crm.companies.delete') && $user->tenant_id === $company->tenant_id;
    }
}
