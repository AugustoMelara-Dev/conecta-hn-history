<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dynamic\DynamicRecord;
use App\Models\User;

class DynamicRecordPolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function view(User $user, DynamicRecord $dynamicRecord): bool
    {
        return $this->ownsResource($user, $dynamicRecord);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function update(User $user, DynamicRecord $dynamicRecord): bool
    {
        return $this->ownsResource($user, $dynamicRecord);
    }

    public function delete(User $user, DynamicRecord $dynamicRecord): bool
    {
        return $this->update($user, $dynamicRecord);
    }

    public function restore(User $user, DynamicRecord $dynamicRecord): bool
    {
        return $this->update($user, $dynamicRecord);
    }

    public function forceDelete(User $user, DynamicRecord $dynamicRecord): bool
    {
        return $this->update($user, $dynamicRecord)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $dynamicRecord));
    }
}
