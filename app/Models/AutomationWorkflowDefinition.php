<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AutomationWorkflowDefinition - Definición de un workflow de automation.
 */
class AutomationWorkflowDefinition extends Model
{
    use HasUuids;

    protected $table = 'automation_workflow_definitions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'workflow_key',
        'current_draft_version_id',
        'current_published_version_id',
        'created_by',
        'updated_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AutomationWorkflowVersion::class, 'definition_id');
    }

    public function currentDraftVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'current_draft_version_id');
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflowVersion::class, 'current_published_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationWorkflowRun::class, 'workflow_key', 'workflow_key');
    }
}
