<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Crm\CanonicalCrmPermissionGate;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;

class ContactPolicy
{
    public function __construct(private readonly CanonicalCrmPermissionGate $permissionGate) {}

    public function viewAny(User $user): bool
    {
        return $this->authorized($user, 'crm.contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->authorized($user, 'crm.contacts.view')
            && $this->belongsToActiveTenant($contact);
    }

    public function create(User $user): bool
    {
        return $this->authorized($user, 'crm.contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->authorized($user, 'crm.contacts.update')
            && $this->belongsToActiveTenant($contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->authorized($user, 'crm.contacts.delete')
            && $this->belongsToActiveTenant($contact);
    }

    private function authorized(User $user, string $permission): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && $this->permissionGate->allows($user, $tenant, $permission);
    }

    private function belongsToActiveTenant(Contact $contact): bool
    {
        $tenant = $this->currentTenant();

        return $tenant instanceof Tenant && (string) $contact->tenant_id === (string) $tenant->getKey();
    }

    private function currentTenant(): ?Tenant
    {
        if (! app()->bound('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        return $tenant instanceof Tenant && (bool) $tenant->is_active ? $tenant : null;
    }
}
