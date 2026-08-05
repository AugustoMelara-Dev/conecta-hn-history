<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Role $role
 */
class TenantMembership extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'role',
        'preset_key',
        'granted_permissions',
        'status',
        'invited_at',
        'accepted_at',
        'suspended_at',
        'revoked_at',
    ];

    protected $guarded = ['tenant_id', 'revoked_by_user_id'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'granted_permissions' => 'array',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAccepted($query)
    {
        return $query
            ->whereNotNull('accepted_at')
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }

    public function isOwner(): bool
    {
        return $this->role->value === Role::OWNER->value;
    }

    /**
     * @return array<int, string>
     */
    public function extraPermissions(): array
    {
        return collect((array) ($this->getAttribute('granted_permissions') ?? []))
            ->filter(static fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->unique()
            ->values()
            ->all();
    }
}
