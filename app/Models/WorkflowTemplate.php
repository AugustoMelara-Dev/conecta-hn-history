<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowTemplate - Template global de workflow (tenant_id null).
 */
class WorkflowTemplate extends Model
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

    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }
}
