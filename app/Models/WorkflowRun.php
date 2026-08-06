<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowRun - Alias/legacy de AutomationWorkflowRun.
 */
class WorkflowRun extends AutomationWorkflowRun
{
    protected $table = 'automation_workflow_runs';
}
