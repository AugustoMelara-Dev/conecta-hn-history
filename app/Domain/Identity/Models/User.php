<?php

declare(strict_types=1);

/**
 * User Model - Identity Domain (Phase 2: Domain Migration)
 *
 * MIGRATION NOTE: This file was moved from App\Models\User to
 * App\Domain\Identity\Models\User as part of the Modular Monolith refactoring.
 *
 * DEPENDENCIES UPDATED:
 * - config/auth.php must reference this new namespace
 * - AppServiceProvider has enforceMorphMap for polymorphic integrity
 */

namespace App\Domain\Identity\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

// Domain Model Imports (Cross-Context via Shared contracts or direct reference)
use App\Domain\TenantGovernance\Models\Tenant;
use App\Models\Favorite;

/**
 * User Model - Identity & Access Authority
 *
 * This model serves as the Single Source of Truth for:
 * 1. Authorization (via Enum-based Identity Helpers)
 * 2. Multi-tenancy Context Resolution (Strict Ownership)
 */
class User extends Authenticatable implements MustVerifyEmail
{
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
            'role' => UserRole::class,
            'tenant_id' => \App\Domain\Shared\Casts\TenantIdCast::class,
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * CRITICAL: Override required because Laravel cannot auto-discover
     * factories in custom Domain namespace structure.
     */
    protected static function newFactory()
    {
        return \App\Domain\Identity\Database\Factories\UserFactory::new();
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
            throw new \DomainException("User is already active.");
        }

        $this->banned_at = null;
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
}
