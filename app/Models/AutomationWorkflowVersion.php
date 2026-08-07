<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AutomationWorkflowVersion - Versión de un workflow (draft/published).
 */
class AutomationWorkflowVersion extends Model
{
    use HasUuids;

    protected $table = 'automation_workflow_versions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'definition_id',
        'tenant_id',
        'workflow_key',
        'version',
        'status',
        'definition',
        'published_at',
        'published_by',
        'created_by',
    ];

    protected $casts = [
        'definition' => 'array',
        'published_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowDefinition::class, 'definition_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
