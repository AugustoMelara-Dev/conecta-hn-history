<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CampaignDispatchRun extends Model
{
    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'run_key',
        'status',
        'cursor_profile_id',
        'queued_count',
        'completed_at',
    ];

    protected $casts = [
        'cursor_profile_id' => 'integer',
        'queued_count' => 'integer',
        'completed_at' => 'datetime',
    ];
}
