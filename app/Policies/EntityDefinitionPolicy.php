<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dynamic\EntityDefinition;
use App\Models\User;

class EntityDefinitionPolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function view(User $user, EntityDefinition $entityDefinition): bool
    {
        if ($entityDefinition->tenant_id === null) {
            return $entityDefinition->status === 'published';
        }

        return $this->ownsResource($user, $entityDefinition);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function update(User $user, EntityDefinition $entityDefinition): bool
    {
        if ($entityDefinition->tenant_id === null || (bool) $entityDefinition->is_locked) {
            return false;
        }

        return $this->ownsResource($user, $entityDefinition);
    }

    public function delete(User $user, EntityDefinition $entityDefinition): bool
    {
        return $this->update($user, $entityDefinition);
    }

    public function restore(User $user, EntityDefinition $entityDefinition): bool
    {
        return $this->update($user, $entityDefinition);
    }

    public function forceDelete(User $user, EntityDefinition $entityDefinition): bool
    {
        return $this->update($user, $entityDefinition)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $entityDefinition));
    }
}
