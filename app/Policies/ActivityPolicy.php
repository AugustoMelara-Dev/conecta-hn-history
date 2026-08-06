<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrmActivity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('crm.activities.view');
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $user->tenant_id === $activity->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('crm.activities.create');
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm.activities.update') && $user->tenant_id === $activity->tenant_id;
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->can('crm.activities.delete') && $user->tenant_id === $activity->tenant_id;
    }
}
