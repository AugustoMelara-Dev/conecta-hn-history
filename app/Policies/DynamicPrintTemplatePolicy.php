<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\User;

class DynamicPrintTemplatePolicy extends TenantAwarePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $this->isAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function view(User $user, DynamicPrintTemplate $template): bool
    {
        if ($template->tenant_id === null) {
            return $template->status === 'published';
        }

        return $this->ownsResource($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    public function update(User $user, DynamicPrintTemplate $template): bool
    {
        if ($template->tenant_id === null || (bool) $template->is_locked) {
            return false;
        }

        return $this->ownsResource($user, $template);
    }

    public function delete(User $user, DynamicPrintTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    public function restore(User $user, DynamicPrintTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    public function forceDelete(User $user, DynamicPrintTemplate $template): bool
    {
        return $this->update($user, $template)
            && $this->isTenantOwner($user, $this->resolveTenant($user, $template));
    }
}
