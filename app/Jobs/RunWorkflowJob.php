<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Automation\Contracts\WorkflowRuntimeInterface;
use App\Domain\Automation\WorkflowStartRequest;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  list<string>  $actorRoles
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $workflowKey,
        public readonly string $workflowVersion,
        public readonly string $actorId,
        public readonly array $actorRoles,
        public readonly string $plan,
        public readonly string $idempotencyKey,
        public readonly array $payload = [],
    ) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(WorkflowRuntimeInterface $runtime): void
    {
        $runtime->start(new WorkflowStartRequest(
            tenantId: $this->tenantId,
            workflowKey: $this->workflowKey,
            workflowVersion: $this->workflowVersion,
            actorId: $this->actorId,
            actorRoles: $this->actorRoles,
            plan: $this->plan,
            channel: 'web',
            idempotencyKey: $this->idempotencyKey,
            trigger: 'manual',
            payload: $this->payload,
        ));
    }
}
