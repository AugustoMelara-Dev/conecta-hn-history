<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CrmDeal extends Model
{
    use BelongsToTenant, LogsActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
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

    protected $guarded = ['tenant_id'];

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
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'crm_stage_id');
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
        return $this->hasMany(CustomerNote::class)->latest('pinned_at')->latest('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CustomerTask::class)->latest('sla_due_at')->latest('created_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('crm_deals')
            ->logOnly([
                'tenant_id',
                'crm_pipeline_id',
                'crm_stage_id',
                'customer_profile_id',
                'lead_id',
                'owner_user_id',
                'title',
                'value',
                'status',
                'expected_close_at',
                'closed_at',
                'lost_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => "crm_deal.{$eventName}");
    }
}
