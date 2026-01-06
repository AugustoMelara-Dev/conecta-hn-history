<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Models;

use App\Domain\Identity\Models\User as DomainUser;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser as FilamentUserContract;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Infrastructure User Model (Filament Adapter)
 *
 * This class extends the pure Domain User entity to add
 * Filament-specific interfaces and logic. This keeps the
 * Domain layer clean of UI dependencies.
 */
final class FilamentUser extends DomainUser implements FilamentUserContract, HasTenants
{
    /**
     * The table associated with the model.
     * Hardcoded because we share the same table as the parent.
     *
     * @var string
     */
    protected $table = 'users';

    // ═══════════════════════════════════════════════════════════════════════
    // PANEL ACCESS CONTROL (Filament Specific)
    // ═══════════════════════════════════════════════════════════════════════

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            DomainUser::PANEL_ADMIN => $this->isSuperAdmin(),
            DomainUser::PANEL_APP   => $this->isTenantOwner() || $this->isSuperAdmin(),
            default                 => false,
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TENANCY RELATIONSHIPS & LOGIC (Filament Specific)
    // ═══════════════════════════════════════════════════════════════════════

    public function getTenants(Panel $panel): Collection
    {
        $tenant = $this->tenant;

        return $tenant ? collect([$tenant]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenant_id === $tenant->id || $this->isSuperAdmin();
    }
}
