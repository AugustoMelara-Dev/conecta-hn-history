<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Models\AutomationWorkflowDefinition;
use Illuminate\Console\Command;

class ScheduleWorkflowExecution extends Command
{
    protected $signature = 'workflows:schedule {workflowId} {--cron=}';

    protected $description = 'Schedule a workflow to run on a cron expression';

    public function handle(): int
    {
        $workflow = AutomationWorkflowDefinition::find($this->argument('workflowId'));
        if (! $workflow) {
            $this->error("Workflow not found.");
            return self::FAILURE;
        }
        $workflow->update(['cron_expression' => $this->option('cron')]);
        $this->info("Workflow scheduled.");
        return self::SUCCESS;
    }
}
