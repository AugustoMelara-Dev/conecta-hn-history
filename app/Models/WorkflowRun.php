<?php

declare(strict_types=1);

namespace App\Models;

/**
 * WorkflowRun - Alias/legacy de AutomationWorkflowRun.
 */
class WorkflowRun extends AutomationWorkflowRun
{
    protected $table = 'automation_workflow_runs';
}
