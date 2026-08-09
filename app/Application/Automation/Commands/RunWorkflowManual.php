<?php

declare(strict_types=1);

namespace App\Application\Automation\Commands;

use App\Jobs\RunWorkflowJob;
use App\Models\AutomationWorkflowDefinition;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RunWorkflowManual extends Command
{
    protected $signature = 'workflows:run {workflowId} {--actor=} {--payload=}';

    protected $description = 'Manually run a workflow';

    public function handle(): int
    {
        $workflow = AutomationWorkflowDefinition::query()
            ->withoutGlobalScopes()
            ->with(['currentPublishedVersion', 'tenant'])
            ->find($this->argument('workflowId'));
        if (! $workflow || ! $workflow->currentPublishedVersion || ! $workflow->tenant) {
            $this->error('Published workflow not found.');

            return self::FAILURE;
        }

        $actorId = (string) $this->option('actor');
        $actor = User::query()->withoutGlobalScopes()->find($actorId);
        if (! $actor || ! $actor->canAccessTenant($workflow->tenant)) {
            $this->error('Actor is not authorized for this workflow tenant.');

            return self::FAILURE;
        }

        try {
            $payload = $this->option('payload')
                ? json_decode((string) $this->option('payload'), true, 512, JSON_THROW_ON_ERROR)
                : [];
        } catch (\JsonException) {
            $this->error('Payload must be a valid JSON object.');

            return self::FAILURE;
        }
        if (! is_array($payload)) {
            $this->error('Payload must be a valid JSON object.');

            return self::FAILURE;
        }

        $role = $actor->getAttribute('role');
        RunWorkflowJob::dispatch(
            tenantId: (string) $workflow->tenant_id,
            workflowKey: (string) $workflow->workflow_key,
            workflowVersion: (string) $workflow->currentPublishedVersion->version,
            actorId: (string) $actor->getKey(),
            actorRoles: [(string) ($role instanceof \BackedEnum ? $role->value : $role)],
            plan: (string) ($workflow->tenant->getRawOriginal('plan_type') ?? 'semilla'),
            idempotencyKey: (string) Str::uuid(),
            payload: $payload,
        );

        $this->info('Workflow queued for canonical execution.');

        return self::SUCCESS;
    }
}
