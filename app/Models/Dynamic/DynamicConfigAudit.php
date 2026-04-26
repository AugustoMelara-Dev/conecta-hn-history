<?php

declare(strict_types=1);

namespace App\Models\Dynamic;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicConfigAudit extends Model
{
    use HasFactory;

    protected $table = 'dynamic_config_audits';

    protected $fillable = [
        'tenant_id',
        'actor_id',
        'action_key',
        'resource_type',
        'resource_id',
        'event_name',
        'request_id',
        'ip_hash',
        'before_json',
        'after_json',
        'json_patch',
        'metadata_json',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'json_patch' => 'array',
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

