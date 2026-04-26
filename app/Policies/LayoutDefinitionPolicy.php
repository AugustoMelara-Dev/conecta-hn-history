<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\LayoutDefinition;
use App\Models\User;

class LayoutDefinitionPolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function view(User $user, LayoutDefinition $layoutDefinition): bool
    {
        return $this->canAccessEntity($user, $layoutDefinition->entityDefinition);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function update(User $user, LayoutDefinition $layoutDefinition): bool
    {
        return $this->canManageEntity($user, $layoutDefinition->entityDefinition);
    }

    public function delete(User $user, LayoutDefinition $layoutDefinition): bool
    {
        return $this->update($user, $layoutDefinition);
    }

    public function restore(User $user, LayoutDefinition $layoutDefinition): bool
    {
        return $this->update($user, $layoutDefinition);
    }

    public function forceDelete(User $user, LayoutDefinition $layoutDefinition): bool
    {
        return $this->update($user, $layoutDefinition)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $layoutDefinition));
    }

    private function canAccessEntity(User $user, ?EntityDefinition $entityDefinition): bool
    {
        if (! $entityDefinition instanceof EntityDefinition) {
            return false;
        }

        if ($entityDefinition->tenant_id === null) {
            return $entityDefinition->status === 'published';
        }

        return $this->ownsResource($user, $entityDefinition);
    }

    private function canManageEntity(User $user, ?EntityDefinition $entityDefinition): bool
    {
        if (! $entityDefinition instanceof EntityDefinition) {
            return false;
        }

        if ($entityDefinition->tenant_id === null || (bool) $entityDefinition->is_locked) {
            return false;
        }

        return $this->ownsResource($user, $entityDefinition);
    }
}
