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
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * User Model - Identity & Access Authority
 *
 * This model serves as the Single Source of Truth for:
 * 1. Authorization (via Enum-based Identity Helpers)
 * 2. Multi-tenancy Context Resolution (Strict Ownership)
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasPushSubscriptions, HasUuids, Notifiable;

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
            'role' => UserRole::class,
            'tenant_id' => \App\Infrastructure\Persistence\Eloquent\Casts\TenantIdCast::class,
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // IDENTITY & GOVERNANCE HELPERS (Single Source of Truth)
    // ═══════════════════════════════════════════════════════════════════════

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
        if ($this->tenant_id === null) {
            return false;
        }

        $tenant = $this->tenant;

        if (! $tenant) {
            return false;
        }

        return (int) $tenant->user_id === (int) $this->id;
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
     * @param string $hashedPassword The already-hashed password string.
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

    // ═══════════════════════════════════════════════════════════════════════
    // SOCIAL & INTERACTION LOGIC
    // ═══════════════════════════════════════════════════════════════════════

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
     * @param int $tenantId The tenant to check balance for
     * @return int Current point balance (can be negative if over-redeemed)
     */
    public function loyaltyBalance(int $tenantId): int
    {
        return (int) $this->loyaltyLedger()
            ->where('tenant_id', $tenantId)
            ->sum('amount');
    }

    /**
     * Get active (non-expired) loyalty points balance for a tenant.
     * 
     * @param int $tenantId The tenant to check balance for
     * @return int Active point balance
     */
    public function activeLoyaltyBalance(int $tenantId): int
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
