<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmDeal extends Model
{
    use BelongsToTenant;

    public const STATUS_OPEN = 'open';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'tenant_id',
        'crm_pipeline_id',
        'crm_stage_id',
        'customer_profile_id',
        'lead_id',
        'owner_user_id',
        'location_id',
        'title',
        'value',
        'currency',
        'status',
        'source',
        'expected_close_at',
        'closed_at',
        'lost_reason',
        'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_at' => 'date',
        'closed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id')->withoutGlobalScopes();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'crm_stage_id')->withoutGlobalScopes();
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class)->withoutGlobalScopes();
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->withoutGlobalScopes()->latest('pinned_at')->latest('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomerTask::class)->withoutGlobalScopes()->latest('sla_due_at')->latest('created_at');
    }
}
