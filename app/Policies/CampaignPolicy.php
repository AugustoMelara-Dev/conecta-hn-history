<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    /**
     * Determine whether the user can view any campaigns.
     * CRITICAL: Only PRO tenants can access campaigns.
     */
    public function viewAny(User $user): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        return $tenant->is_pro === true;
    }

    /**
     * Determine whether the user can view the campaign.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant || ! $tenant->is_pro) {
            return false;
        }

        return $campaign->tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user can create campaigns.
     */
    public function create(User $user): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        return $tenant->is_pro === true;
    }

    /**
     * Determine whether the user can update the campaign.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant || ! $tenant->is_pro) {
            return false;
        }

        return $campaign->tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user can delete the campaign.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        if (! $tenant || ! $tenant->is_pro) {
            return false;
        }

        return $campaign->tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user can restore the campaign.
     */
    public function restore(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    /**
     * Determine whether the user can permanently delete the campaign.
     */
    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $this->delete($user, $campaign);
    }
}
