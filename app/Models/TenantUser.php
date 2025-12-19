<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'can_manage_products',
        'can_manage_orders',
        'can_view_analytics',
        'can_manage_settings',
        'can_manage_team',
        'can_manage_billing',
        'invited_at',
        'accepted_at',
        'invitation_token',
        'invited_by',
        'last_access_at',
    ];

    protected $casts = [
        'can_manage_products' => 'boolean',
        'can_manage_orders' => 'boolean',
        'can_view_analytics' => 'boolean',
        'can_manage_settings' => 'boolean',
        'can_manage_team' => 'boolean',
        'can_manage_billing' => 'boolean',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_access_at' => 'datetime',
    ];

    // ═══ RELACIONES ═══

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ═══ SCOPES ═══

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }

    // ═══ ROLE HELPERS ═══

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    // ═══ PERMISSION HELPERS ═══

    public function canManageProducts(): bool
    {
        return $this->isOwner() || $this->can_manage_products;
    }

    public function canManageOrders(): bool
    {
        return $this->isOwner() || $this->can_manage_orders;
    }

    public function canViewAnalytics(): bool
    {
        return $this->isOwner() || $this->isManager() || $this->can_view_analytics;
    }

    public function canManageSettings(): bool
    {
        return $this->isOwner() || $this->can_manage_settings;
    }

    public function canManageTeam(): bool
    {
        return $this->isOwner() || $this->can_manage_team;
    }

    public function canManageBilling(): bool
    {
        return $this->isOwner() || $this->can_manage_billing;
    }

    // ═══ INVITATION HELPERS ═══

    public function isPendingAcceptance(): bool
    {
        return $this->accepted_at === null;
    }

    public function accept(): void
    {
        $this->update([
            'accepted_at' => now(),
            'invitation_token' => null,
        ]);
    }

    public function recordAccess(): void
    {
        $this->update(['last_access_at' => now()]);
    }

    /**
     * Genera un token de invitación único
     */
    public static function generateInvitationToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

