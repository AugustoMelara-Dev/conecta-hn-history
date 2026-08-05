<?php

declare(strict_types=1);

/**
 * User Model - Canonical Location (app/Models)
 *
 * MIGRATION: Moved from App\Domain\Identity\Models\User
 * as part of Domain Purification (2026-01-07).
 */

namespace App\Models;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Casts\TenantIdCast;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser as FilamentUserContract;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * User Model - Identity & Access Authority
 *
 * This model serves as the Single Source of Truth for:
 * 1. Authorization (via Enum-based Identity Helpers)
 * 2. Multi-tenancy Context Resolution (Strict Ownership)
 */
class User extends Authenticatable implements FilamentUserContract, HasTenants, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasUuids, Notifiable;
    use TwoFactorAuthenticatable {
        hasEnabledTwoFactorAuthentication as private fortifyHasEnabledTwoFactorAuthentication;
    }

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

    public function fill(array $attributes)
    {
        if (array_key_exists('tenant_id', $attributes) && ! static::isUnguarded()) {
            throw new MassAssignmentException('Add [tenant_id] to fillable property to allow mass assignment on ['.static::class.'].');
        }

        return parent::fill($attributes);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     *
     * ═══════════════════════════════════════════════════════════════════════════
     * ⚠️  ARQUITECTURA DDD - NO MODIFICAR SIN AUTORIZACIÓN DEL ARQUITECTO  ⚠️
     * ═══════════════════════════════════════════════════════════════════════════
     *
     * PROHIBIDO: 'password' => 'hashed'
     *
     * El hashing de contraseñas es responsabilidad EXCLUSIVA de los Handlers
     * de la Capa de Aplicación (RegisterUserHandler, UpdatePasswordHandler).
     *
     * Este modelo Eloquent debe actuar como un "Dumb Data Mapper" (Mapeador
     * de Datos Tonto) que persiste EXACTAMENTE lo que el Dominio le entrega,
     * sin transformaciones implícitas.
     *
     * Reactivar el cast 'hashed' causará DOBLE HASHING y corromperá todas
     * las credenciales, haciendo imposible el login.
     *
     * @see App\Application\Identity\Handlers\RegisterUserHandler
     * @see App\Domain\Shared\PasswordHasherInterface
     * ═══════════════════════════════════════════════════════════════════════════
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'tenant_id' => TenantIdCast::class,
        ];
    }

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        $attributes = $this->getAttributes();

        if ($this->exists
            && (! array_key_exists('two_factor_secret', $attributes)
                || ! array_key_exists('two_factor_confirmed_at', $attributes))) {
            $this->refresh();
        }

        return $this->fortifyHasEnabledTwoFactorAuthentication();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return UserFactory::new();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // IDENTITY & GOVERNANCE HELPERS (Single Source of Truth)
    // ═══════════════════════════════════════════════════════════════════════

    public function isSuperAdmin(): bool
    {
        $role = $this->role instanceof UserRole
            ? $this->role
            : UserRole::tryFrom((string) $this->role);

        if ($role !== UserRole::SUPER_ADMIN) {
            return false;
        }

        $allowedEmails = array_map(
            static fn (string $email): string => strtolower(trim($email)),
            config('auth.super_admins', [])
        );

        return in_array(strtolower((string) $this->email), $allowedEmails, true);
    }

    /**
     * Legacy role-policy compatibility while the canonical role value lives on
     * the UserRole enum.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $current = $this->role instanceof UserRole
            ? $this->role->value
            : (string) $this->role;

        $normalize = static fn (string $role): string => strtolower(str_replace([' ', '-'], '_', $role));

        $expected = array_map($normalize, (array) $roles);

        return in_array($normalize($current), $expected, true);
    }

    public function isTenantOwner(): bool
    {
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
            'remember_token' => Str::random(60),
        ])->save();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TENANCY RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════

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
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    public function memberTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships', 'user_id', 'tenant_id')
            ->withPivot(['role', 'preset_key', 'granted_permissions', 'accepted_at'])
            ->withTimestamps();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Tenant) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ((string) $tenant->user_id === (string) $this->id) {
            return true;
        }

        if (! Schema::hasTable('tenant_memberships')) {
            return false;
        }

        return $this->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('accepted_at')
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->exists();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SOCIAL & INTERACTION LOGIC
    // ═══════════════════════════════════════════════════════════════════════

    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return Tenant::query()
                ->orderBy('name')
                ->get();
        }

        $owned = $this->tenants()->orderBy('name')->get();
        $member = Schema::hasTable('tenant_memberships')
            ? $this->memberTenants()
                ->wherePivotNotNull('accepted_at')
                ->orderBy('name')
                ->get()
            : collect();

        return $owned
            ->merge($member)
            ->unique(fn (Tenant $tenant): string => (string) $tenant->getKey())
            ->values();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            self::PANEL_ADMIN => $this->isSuperAdmin()
                && (! (bool) config('security.admin_mfa_required', false) || $this->hasEnabledTwoFactorAuthentication()),
            self::PANEL_APP => $this->tenants()->exists()
                || (Schema::hasTable('tenant_memberships') && $this->tenantMemberships()->whereNotNull('accepted_at')->exists())
                || $this->isSuperAdmin(),
            default => false,
        };
    }

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

    public function toggleFavorite(Tenant $tenant): bool
    {
        $existing = $this->favorites()
            ->where('favoritable_id', $tenant->id)
            ->where('favoritable_type', Tenant::class)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $this->favorites()->create([
            'favoritable_id' => $tenant->id,
            'favoritable_type' => Tenant::class,
        ]);

        return true;
    }

    public function following()
    {
        return $this->belongsToMany(Tenant::class, 'follows')
            ->withTimestamps();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LOYALTY SYSTEM
    // ═══════════════════════════════════════════════════════════════════════

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
     * @param  string  $tenantId  The tenant UUID to check balance for
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
     * @param  string  $tenantId  The tenant UUID to check balance for
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
