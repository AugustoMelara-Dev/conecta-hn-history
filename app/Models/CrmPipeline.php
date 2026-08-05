<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmPipeline extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'key',
        'description',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $guarded = ['tenant_id'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }
}
