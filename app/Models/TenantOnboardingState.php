<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantOnboardingState extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'progress',
        'next_action_key',
        'next_action_title',
        'next_action_copy',
        'next_action_href',
        'milestones',
        'last_completed_at',
        'first_run_started_at',
        'first_run_dismissed_at',
        'first_run_step_key',
        'first_run_completed_steps',
        'first_run_skipped_reason',
        'first_run_completed_at',
        'first_run_last_seen_at',
        'first_run_abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'milestones' => 'array',
            'last_completed_at' => 'datetime',
            'first_run_started_at' => 'datetime',
            'first_run_dismissed_at' => 'datetime',
            'first_run_completed_steps' => 'array',
            'first_run_completed_at' => 'datetime',
            'first_run_last_seen_at' => 'datetime',
            'first_run_abandoned_at' => 'datetime',
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
}
