<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Lead;
use App\Models\User;

/**
 * Tenant-aware authorization for leads/inquiries.
 *
 * View permissions follow the customer directory capability; mutations require
 * marketing management permissions on the current tenant.
 */
class LeadPolicy extends TenantAwarePolicy
{
    /**
     * Determine whether the user can view any leads.
     */
    public function viewAny(User $user): bool
    {
        return $this->allows($user, Capability::CUSTOMER_DIRECTORY, Permission::CUSTOMERS_VIEW);
    }

    /**
     * Determine whether the user can view the lead.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $this->allows($user, Capability::CUSTOMER_DIRECTORY, Permission::CUSTOMERS_VIEW, $lead);
    }

    /**
     * Determine whether the user can create leads.
     */
    public function create(User $user): bool
    {
        return $this->allows($user, Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE);
    }

    /**
     * Determine whether the user can update the lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $this->allows($user, Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE, $lead);
    }

    /**
     * Determine whether the user can delete the lead.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $this->allows($user, Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE, $lead);
    }

    /**
     * Determine whether the user can restore the lead.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return $this->allows($user, Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE, $lead);
    }

    /**
     * Determine whether the user can permanently delete the lead.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return $this->allows($user, Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE, $lead)
            && $this->isTenantOwner($user);
    }
}

