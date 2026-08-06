<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkflowDefinition - Alias/legacy de AutomationWorkflowDefinition.
 */
class WorkflowDefinition extends AutomationWorkflowDefinition
{
    protected $table = 'automation_workflow_definitions';
}
