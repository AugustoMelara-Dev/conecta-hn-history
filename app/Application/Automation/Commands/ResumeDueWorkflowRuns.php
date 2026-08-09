<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

// @withoutGlobalScopes-safety JOB_GLOBAL_POLL: scans due runs globally and carries each selected tenant ID into the queued job.

use App\Jobs\ResumeWorkflowJob;
use App\Models\AutomationWorkflowRun;
use Illuminate\Console\Command;

final class ResumeDueWorkflowRuns extends Command
{
    protected $signature = 'workflows:resume-due {--limit=500}';

    protected $description = 'Queue canonical workflow runs whose waiting period has elapsed';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $queued = 0;

        AutomationWorkflowRun::query()
            ->withoutGlobalScopes()
            ->where('status', 'waiting')
            ->whereNotNull('actor_id')
            ->whereNotNull('waiting_until')
            ->where('waiting_until', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'tenant_id', 'actor_id', 'plan', 'channel'])
            ->each(function (AutomationWorkflowRun $run) use (&$queued): void {
                ResumeWorkflowJob::dispatch(
                    tenantId: (string) $run->tenant_id,
                    runId: (string) $run->getKey(),
                    actorId: (string) $run->actor_id,
                    plan: (string) $run->plan,
                    channel: (string) $run->channel,
                );
                $queued++;
            });

        $this->info("Queued {$queued} due workflow run(s).");

        return self::SUCCESS;
    }
}
