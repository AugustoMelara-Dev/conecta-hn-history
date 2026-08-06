<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrmDeal;
use App\Models\User;

class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.deals.view');
    }

    public function view(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm.deals.view') && $user->tenant_id === $deal->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm.deals.create');
    }

    public function update(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm.deals.update') && $user->tenant_id === $deal->tenant_id;
    }

    public function delete(User $user, CrmDeal $deal): bool
    {
        return $user->can('crm.deals.delete') && $user->tenant_id === $deal->tenant_id;
    }
}
