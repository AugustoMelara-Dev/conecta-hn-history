<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Enums\Role;
use App\Infrastructure\Persistence\Eloquent\Traits\AllowsTrustedTenantAssignment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Role $role
 */
class TenantMembership extends Model
{
    use AllowsTrustedTenantAssignment, HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'role',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'accepted_at' => 'datetime',
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
        return $query->whereNotNull('accepted_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at');
    }

    public function isOwner(): bool
    {
        return $this->role->value === Role::OWNER->value;
    }
}
