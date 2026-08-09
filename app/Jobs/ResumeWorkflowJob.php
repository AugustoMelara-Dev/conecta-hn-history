<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Automation\Contracts\WorkflowRuntimeInterface;
use App\Domain\Automation\WorkflowResumeRequest;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

final class ResumeWorkflowJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $runId,
        public readonly string $actorId,
        public readonly string $plan,
        public readonly string $channel,
    ) {}

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->runId;
    }

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(WorkflowRuntimeInterface $runtime): void
    {
        $actor = User::query()->withoutGlobalScopes()->find($this->actorId);
        $tenant = Tenant::query()->withoutGlobalScopes()->find($this->tenantId);
        if (! $actor || ! $tenant || ! $actor->canAccessTenant($tenant)) {
            throw new RuntimeException('Workflow actor is no longer authorized for this tenant.');
        }
        $role = $actor->getAttribute('role');

        $runtime->resume($this->runId, new WorkflowResumeRequest(
            tenantId: $this->tenantId,
            actorId: $this->actorId,
            actorRoles: [(string) ($role instanceof \BackedEnum ? $role->value : $role)],
            plan: $this->plan,
            channel: $this->channel,
        ));
    }
}
