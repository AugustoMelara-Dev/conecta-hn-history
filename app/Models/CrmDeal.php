<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Crm\Enums\DealStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmDeal extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'crm_deals';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'pipeline_id',
        'stage_id',
        'contact_id',
        'company_id',
        'owner_id',
        'name',
        'value',
        'currency',
        'probability',
        'expected_close_date',
        'status',
        'won_at',
        'lost_at',
        'lost_reason',
        'description',
        'custom_fields',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'deal_id');
    }

    public function getStatusEnumAttribute(): DealStatus
    {
        return DealStatus::from($this->status);
    }

    public function getWeightedValueAttribute(): float
    {
        return (float) $this->value * ($this->probability / 100);
    }

    public function isOpen(): bool
    {
        return $this->status === DealStatus::Open->value;
    }

    public function isWon(): bool
    {
        return $this->status === DealStatus::Won->value;
    }

    public function isLost(): bool
    {
        return $this->status === DealStatus::Lost->value;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', DealStatus::Open->value);
    }

    public function scopeWon($query)
    {
        return $query->where('status', DealStatus::Won->value);
    }

    public function scopeLost($query)
    {
        return $query->where('status', DealStatus::Lost->value);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
