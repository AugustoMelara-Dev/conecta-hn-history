<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;

class AppointmentPolicy
{
    public function __construct(private TenantPermissionGuardInterface $guard) {}

    public function viewAny(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::APPOINTMENT_BOOKING, Permission::APPOINTMENTS_VIEW);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->tenant() instanceof Tenant
            && $this->guard->allows($user, $this->tenant(), Capability::APPOINTMENT_BOOKING, Permission::APPOINTMENTS_MANAGE);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->create($user);
    }

    private function tenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
