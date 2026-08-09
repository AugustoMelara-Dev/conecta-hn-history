<?php

declare(strict_types=1);

namespace App\Models;

/**
 * WorkflowDefinition - Alias/legacy de AutomationWorkflowDefinition.
 */
class WorkflowDefinition extends AutomationWorkflowDefinition
{
    protected $table = 'automation_workflow_definitions';
}
