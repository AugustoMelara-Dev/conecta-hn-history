<?php

declare(strict_types=1);

/**
 * User Model - Canonical Location (app/Models)
 *
 * MIGRATION: Moved from App\Domain\Identity\Models\User
 * as part of Domain Purification (2026-01-07).
 */

namespace App\Models;

use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Domain\Identity\Enums\UserRole;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * User Model - Identity & Access Authority
 *
 * This model serves as the Single Source of Truth for:
 * 1. Authorization (via Enum-based Identity Helpers)
 * 2. Multi-tenancy Context Resolution (Strict Ownership)
 */
class User extends Authenticatable implements HasTenants, MustVerifyEmail, TenantAwareAuthenticatable
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasUuids, Notifiable;

    /**
     * UUID Configuration: Disable Auto-Increment
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Filament Panel Identifiers - Single Source of Truth
     */
    public const PANEL_ADMIN = 'admin';

    public const PANEL_APP = 'app';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'banned_at',
        // SECURITY: tenant_id is GUARDED. Must be set explicitly via $user->tenant_id = X
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     *
     * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     * âš ï¸  ARQUITECTURA DDD - NO MODIFICAR SIN AUTORIZACIÃ“N DEL ARQUITECTO  âš ï¸
     * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     *
     * PROHIBIDO: 'password' => 'hashed'
     *
     * El hashing de contraseÃ±as es responsabilidad EXCLUSIVA de los Handlers
     * de la Capa de AplicaciÃ³n (RegisterUserHandler, UpdatePasswordHandler).
     *
     * Este modelo Eloquent debe actuar como un "Dumb Data Mapper" (Mapeador
     * de Datos Tonto) que persiste EXACTAMENTE lo que el Dominio le entrega,
     * sin transformaciones implÃ­citas.
     *
     * Reactivar el cast 'hashed' causarÃ¡ DOBLE HASHING y corromperÃ¡ todas
     * las credenciales, haciendo imposible el login.
     *
     * @see App\Application\Identity\Handlers\RegisterUserHandler
     * @see App\Domain\Shared\PasswordHasherInterface
     * â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'role' => UserRole::class,
            // 'tenant_id' => \App\Infrastructure\Persistence\Eloquent\Casts\TenantIdCast::class,
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // IDENTITY & GOVERNANCE HELPERS (Single Source of Truth)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function isSuperAdmin(): bool
    {
        $adminEmail = config('auth.super_admin_email');

        if ($adminEmail && strcasecmp($this->email, $adminEmail) === 0) {
            return true;
        }

        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isTenantOwner(): bool
    {
        $membership = $this->tenantMemberships()
            ->where('tenant_id', $this->tenant_id)
            ->where('role', \App\Domain\Access\Enums\Role::OWNER->value)
            ->whereNotNull('accepted_at')
            ->first();

        if ($membership !== null) {
            return true;
        }

        if ($this->tenant_id === null) {
            return false;
        }

        $tenant = $this->tenant;

        if (! $tenant) {
            return false;
        }

        return (string) $tenant->user_id === (string) $this->id;
    }

    /**
     * Domain Action: Reactivate User
     *
     * Mutates state in memory but DOES NOT PERSIST.
     * Persistence belongs to the Application Layer.
     *
     * @throws \DomainException
     */
    public function reactivate(): void
    {
        if ($this->banned_at === null) {
            throw new \DomainException('User is already active.');
        }

        $this->banned_at = null;
    }

    /**
     * Domain Action: Update Password
     *
     * Encapsulates the password persistence and remember_token rotation.
     * Accepts a PRE-HASHED password string. Hashing policy is owned
     * by the Application Layer, NOT this model.
     *
     * @param  string  $hashedPassword  The already-hashed password string.
     *
     * @see App\Application\Identity\Handlers\RegisterUserHandler
     * @see App\Domain\Shared\PasswordHasherInterface
     */
    public function updatePassword(string $hashedPassword): void
    {
        $this->forceFill([
            'password' => $hashedPassword,
            'remember_token' => \Illuminate\Support\Str::random(60),
        ])->save();
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // TENANCY RELATIONSHIPS
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * TenantAware Contract: Explicit tenant identity resolution.
     *
     * Replaces implicit $user->tenant_id access with a type-safe getter,
     * decoupling Actions from the concrete Eloquent schema.
     */
    public function getTenantId(): int|string|null
    {
        return $this->tenant_id;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function memberTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['role', 'accepted_at'])
            ->withTimestamps();
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // FILAMENT MULTI-TENANCY CONTRACT (HasTenants)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function getTenants(Panel $panel): Collection
    {
        $tenants = $this->memberTenants()
            ->wherePivotNotNull('accepted_at')
            ->get();

        if ($tenant = $this->tenant) {
            $tenants->prepend($tenant);
        }

        return $tenants->unique('id')->values();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->tenant_id !== null && (string) $this->tenant_id === (string) $tenant->id) {
            return true;
        }

        return $this->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // SOCIAL & INTERACTION LOGIC
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedTenants()
    {
        return $this->morphedByMany(Tenant::class, 'favoritable', 'favorites')
            ->withTimestamps();
    }

    public function hasFavorited(Tenant $tenant): bool
    {
        return $this->favorites()
            ->where('favoritable_id', $tenant->id)
            ->where('favoritable_type', Tenant::class)
            ->exists();
    }

    public function toggleFavorite(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $tenant;

        $existing = $this->favorites()
            ->where('favoritable_id', $tenantId)
            ->where('favoritable_type', Tenant::class)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $this->favorites()->create([
            'favoritable_id' => $tenantId,
            'favoritable_type' => Tenant::class,
        ]);

        return true;
    }

    public function toggleFollowingTenant(string|int $tenantId): bool
    {
        $changes = $this->following()->toggle($tenantId);

        return ! empty($changes['attached']);
    }

    public function following()
    {
        return $this->belongsToMany(Tenant::class, 'follows')
            ->withTimestamps();
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // LOYALTY SYSTEM
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    /**
     * Get all loyalty ledger entries for this user.
     */
    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(LoyaltyLedger::class);
    }

    /**
     * Get loyalty points balance for a specific tenant.
     *
     * O(1) complexity via Sum Aggregation.
     * Balance = SUM(amount) for this user at this tenant.
     *
     * INVARIANT: Points are tenant-isolated. Points from Tenant A
     * cannot be used at Tenant B.
     *
     * @param  string  $tenantId  The tenant to check balance for
     * @return int Current point balance (can be negative if over-redeemed)
     */
    public function loyaltyBalance(string $tenantId): int
    {
        return (int) $this->loyaltyLedger()
            ->where('tenant_id', $tenantId)
            ->sum('amount');
    }

    /**
     * Get active (non-expired) loyalty points balance for a tenant.
     *
     * @param  string  $tenantId  The tenant to check balance for
     * @return int Active point balance
     */
    public function activeLoyaltyBalance(string $tenantId): int
    {
        return (int) $this->loyaltyLedger()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('amount');
    }
}
