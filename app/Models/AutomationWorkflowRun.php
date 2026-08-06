<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AutomationWorkflowRun - Ejecución de un workflow.
 */
class AutomationWorkflowRun extends Model
{
    use HasUuids;

    protected $table = 'automation_workflow_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'workflow_key',
        'workflow_version_id',
        'workflow_version',
        'actor_id',
        'plan',
        'channel',
        'trigger_source',
        'idempotency_key',
        'status',
        'current_step_key',
        'payload',
        'output',
        'last_error_code',
        'waiting_until',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'output' => 'array',
        'waiting_until' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'workflow_version_id');
    }
}
