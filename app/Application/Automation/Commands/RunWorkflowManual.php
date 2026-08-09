<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Application\Automation\WorkflowExecutor;
use App\Domain\Automation\ValueObjects\AutomationGraph;
use App\Models\AutomationWorkflowDefinition;
use Illuminate\Console\Command;

class RunWorkflowManual extends Command
{
    protected $signature = 'workflows:run {workflowId} {--payload=}';

    protected $description = 'Manually run a workflow';

    public function handle(WorkflowExecutor $executor): int
    {
        $workflow = AutomationWorkflowDefinition::find($this->argument('workflowId'));
        if (! $workflow) {
            $this->error('Workflow not found.');

            return self::FAILURE;
        }

        $graph = AutomationGraph::fromJson($workflow->graph_json ?? '{}');
        $payload = $this->option('payload') ? json_decode($this->option('payload'), true) : [];
        $result = $executor->execute($workflow->id, $graph, $payload);

        $this->info("Run {$result['run_id']} status: {$result['status']}");

        return self::SUCCESS;
    }
}
