<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Models;

use App\Models\User as DomainUser;
use Filament\Models\Contracts\FilamentUser as FilamentUserContract;
use Filament\Models\Contracts\HasTenants;

/**
 * Infrastructure User Model (Filament Adapter)
 *
 * This adapter keeps a stable auth-provider model class for infra wiring,
 * while delegating all access/tenant logic to the canonical Domain user model.
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
}
