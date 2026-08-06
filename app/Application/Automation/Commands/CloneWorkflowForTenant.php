<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Models\AutomationWorkflowDefinition;
use Illuminate\Console\Command;

class CloneWorkflowForTenant extends Command
{
    protected $signature = 'workflows:clone {sourceId} {tenantId}';

    protected $description = 'Clone a workflow (template) to a specific tenant';

    public function handle(): int
    {
        $source = AutomationWorkflowDefinition::find($this->argument('sourceId'));
        if (! $source) {
            $this->error("Source workflow not found.");
            return self::FAILURE;
        }

        $clone = $source->replicate();
        $clone->tenant_id = $this->argument('tenantId');
        $clone->name = $source->name;
        $clone->is_active = false;
        $clone->status = 'draft';
        $clone->save();

        $this->info("Workflow cloned to tenant.");
        return self::SUCCESS;
    }
}
