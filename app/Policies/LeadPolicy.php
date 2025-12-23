<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * LeadPolicy - Tenant-Aware Authorization for Leads/Inquiries
 * 
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY: Enforces tenant ownership for customer inquiry management
 * ══════════════════════════════════════════════════════════════════════════════
 */
class LeadPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any leads.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can view the lead.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $this->ownsResource($user, $lead);
    }

    /**
     * Determine whether the user can create leads.
     */
    public function create(User $user): bool
    {
        return $this->hasAnyTenant($user);
    }

    /**
     * Determine whether the user can update the lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $this->ownsResource($user, $lead);
    }

    /**
     * Determine whether the user can delete the lead.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $this->ownsResource($user, $lead);
    }

    /**
     * Determine whether the user can restore the lead.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return $this->ownsResource($user, $lead);
    }

    /**
     * Determine whether the user can permanently delete the lead.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return $this->ownsResource($user, $lead) && $this->isAdmin($user);
    }
}
