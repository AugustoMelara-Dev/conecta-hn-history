<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImmutableTenantId;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmActivity extends Model
{
    use HasImmutableTenantId, HasUuids;

    protected $table = 'crm_activities';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'deal_id',
        'company_id',
        'owner_id',
        'type',
        'subject',
        'body',
        'due_at',
        'completed_at',
        'priority',
        'outcome',
    ];

    protected $casts = [
        'deal_id' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class, 'contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CrmDeal::class, 'deal_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CrmCompany::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! $this->isCompleted();
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
