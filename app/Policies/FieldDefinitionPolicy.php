<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\User;

class FieldDefinitionPolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function view(User $user, FieldDefinition $fieldDefinition): bool
    {
        return $this->canAccessEntity($user, $fieldDefinition->entityDefinition);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function update(User $user, FieldDefinition $fieldDefinition): bool
    {
        if ((bool) $fieldDefinition->is_locked) {
            return false;
        }

        return $this->canManageEntity($user, $fieldDefinition->entityDefinition);
    }

    public function delete(User $user, FieldDefinition $fieldDefinition): bool
    {
        return $this->update($user, $fieldDefinition);
    }

    public function restore(User $user, FieldDefinition $fieldDefinition): bool
    {
        return $this->update($user, $fieldDefinition);
    }

    public function forceDelete(User $user, FieldDefinition $fieldDefinition): bool
    {
        return $this->update($user, $fieldDefinition)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $fieldDefinition));
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
