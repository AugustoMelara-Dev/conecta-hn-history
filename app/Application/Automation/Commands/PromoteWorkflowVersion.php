<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Models\AutomationWorkflowVersion;
use Illuminate\Console\Command;

class PromoteWorkflowVersion extends Command
{
    protected $signature = 'workflows:promote {versionId}';

    protected $description = 'Promote a workflow version to active';

    public function handle(): int
    {
        $version = AutomationWorkflowVersion::find($this->argument('versionId'));
        if (! $version) {
            $this->error("Version not found.");
            return self::FAILURE;
        }
        $version->update(['is_active' => true]);
        $this->info("Version promoted.");
        return self::SUCCESS;
    }
}
