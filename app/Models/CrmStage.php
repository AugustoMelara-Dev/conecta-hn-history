<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    use BelongsToTenant;

    public const TYPE_OPEN = 'open';
    public const TYPE_WON = 'won';
    public const TYPE_LOST = 'lost';

    protected $fillable = [
        'crm_pipeline_id',
        'name',
        'key',
        'type',
        'probability',
        'is_default',
        'sort_order',
    ];

    protected $guarded = ['tenant_id'];

    protected $casts = [
        'probability' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pipeline(): BelongsTo
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id')->withoutGlobalScopes();
    }

    public function deals(): HasMany
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return $this->hasMany(CrmDeal::class)->withoutGlobalScopes();
    }
}
