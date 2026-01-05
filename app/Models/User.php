<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * User Model - Identity & Access Authority (Phase 6.1 Strict)
 *
 * This model serves as the Single Source of Truth for:
 * 1. Authorization (via Enum-based Identity Helpers)
 * 2. Panel Access (via Protected Constants)
 * 3. Multi-tenancy Context Resolution (Strict Ownership)
 */
class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'tenant_id',
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
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class, // Fail-fast Enum Casting
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // IDENTITY & GOVERNANCE HELPERS (Single Source of Truth)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Determine if the user is the Platform Owner (Super Admin).
     *
     * MECHANISM:
     * 1. Check against secure config variable (Fail-Safe/Recovery).
     * 2. Check against strictly typed Enum role.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        // Fail-Safe: Config-based check allows access even if DB is compromised
        $adminEmail = config('auth.super_admin_email');

        // SECURITY: Case-insensitive comparison (RFC 5321)
        if ($adminEmail && strcasecmp($this->email, $adminEmail) === 0) {
            return true;
        }

        return $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine if the user legitimately OWNS a Business (Tenant).
     *
     * STRICT OWNERSHIP CHECK:
     * Unlike simple membership checks, this strictly verifies that the user
     * is the CREATOR/OWNER of the tenant record.
     *
     * @return bool
     */
    public function isTenantOwner(): bool
    {
        // 1. Structural Integrity Check
        if ($this->tenant_id === null) {
            return false;
        }

        // 2. Optimization: Accessing the PROPERTY ($this->tenant) automatically handles:
        // - Return loaded model if Eager Loaded.
        // - Lazy Load (and cache result) if not loaded.
        // - Return null if not found.
        $tenant = $this->tenant;

        // 3. Referential Integrity Check ("Zombie ID" prevention)
        if (! $tenant) {
            return false;
        }

        // 4. STRICT OWNERSHIP Check (The Critical Fix)
        // Must match the owner ID recorded in the tenant table.
        return (int) $tenant->user_id === (int) $this->id;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PANEL ACCESS CONTROL (Gatekeeper)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Determine if the user can access a specific Filament Panel.
     *
     * ROUTING LOGIC (Strict Match):
     * - Admin Panel: Restricted strictly to Super Admins.
     * - App Panel: Restricted to Tenant Owners (and Super Admins for Support).
     *
     * @param Panel $panel
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            self::PANEL_ADMIN => $this->isSuperAdmin(),
            // Support Exception: Allow Super Admin to access Tenant Panels for debugging
            self::PANEL_APP   => $this->isTenantOwner() || $this->isSuperAdmin(),
            default           => false,
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TENANCY RELATIONSHIPS & LOGIC
    // ═══════════════════════════════════════════════════════════════════════

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Get tenants for Filament panel context.
     *
     * ARCHITECTURE:
     * Enforces strict 1:1 user-to-tenant relationship context.
     * Wraps the single tenant in a collection to satisfy HasTenants interface.
     *
     * @param Panel $panel
     * @return Collection
     */
    public function getTenants(Panel $panel): Collection
    {
        $tenant = $this->tenant;

        return $tenant ? collect([$tenant]) : collect();
    }

    /**
     * Check if user can access a specific tenant.
     *
     * SECURITY:
     * 1. IDOR Prevention: Users can only access their assigned tenant.
     * 2. Support Bypass: Super Admins can access ANY tenant for support.
     *
     * @param Model $tenant
     * @return bool
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenant_id === $tenant->id || $this->isSuperAdmin();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SOCIAL & INTERACTION LOGIC
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get all favorites for this user (polymorphic).
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Get all tenants this user has favorited.
     */
    public function favoritedTenants()
    {
        return $this->morphedByMany(Tenant::class, 'favoritable', 'favorites')
            ->withTimestamps();
    }

    /**
     * Check if user has favorited a specific tenant.
     */
    public function hasFavorited(Tenant $tenant): bool
    {
        return $this->favorites()
            ->where('favoritable_id', $tenant->id)
            ->where('favoritable_type', Tenant::class)
            ->exists();
    }

    /**
     * Toggle favorite status for a tenant.
     *
     * @return bool True if favorited, False if unfavorited
     */
    public function toggleFavorite(Tenant $tenant): bool
    {
        $existing = $this->favorites()
            ->where('favoritable_id', $tenant->id)
            ->where('favoritable_type', Tenant::class)
            ->first();

        if ($existing) {
            $existing->delete();
            return false; // Action: Unfavorited
        }

        $this->favorites()->create([
            'favoritable_id' => $tenant->id,
            'favoritable_type' => Tenant::class,
        ]);

        return true; // Action: Favorited
    }

    /**
     * Get the tenants this user is following.
     */
    public function following()
    {
        return $this->belongsToMany(Tenant::class, 'follows')
            ->withTimestamps();
    }
}
