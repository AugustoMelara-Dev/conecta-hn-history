<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\ToolPolicyEngineInterface;
use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Application\Automation\Contracts\WorkflowRuntimeInterface;
use App\Domain\Automation\WorkflowResumeRequest;
use App\Domain\Automation\WorkflowRunResult;
use App\Domain\Automation\WorkflowStartRequest;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class WorkflowRuntime implements WorkflowRuntimeInterface
{
    private WorkflowRunService $runService;

    /**
     * @param  array<string, callable>  $toolHandlers
     */
    public function __construct(
        private WorkflowDefinitionService $definitions,
        private WorkflowRepositoryInterface $runs,
        private ToolPolicyEngineInterface $policyEngine,
        private array $toolHandlers = [],
    ) {
        $this->runService = new WorkflowRunService($this->runs, $this->policyEngine, $this->toolHandlers);
    }

    public function start(WorkflowStartRequest $request): WorkflowRunResult
    {
        $existing = $this->runs->findWorkflowRunByIdempotency(
            $request->tenantId,
            $request->workflowKey,
            $request->workflowVersion,
            $request->idempotencyKey,
        );

        if ($existing !== null) {
            return new WorkflowRunResult(
                runId: (string) $existing['id'],
                status: (string) $existing['status'],
                currentStepId: isset($existing['current_step_key']) ? (string) $existing['current_step_key'] : null,
                replayed: true,
                waitingUntil: isset($existing['waiting_until']) && $existing['waiting_until'] !== null
                    ? CarbonImmutable::parse((string) $existing['waiting_until'])
                    : null,
                output: is_array($existing['output'] ?? null) ? $existing['output'] : [],
            );
        }

        $version = $this->definitions->publishedVersion($request->tenantId, $request->workflowKey, $request->workflowVersion);
        if ($version === null) {
            throw new RuntimeException('Published workflow version not found.');
        }

        $run = $this->runs->createWorkflowRun(
            $request->tenantId,
            $request->workflowKey,
            (string) $version['id'],
            $request->workflowVersion,
            $request->actorId,
            $request->plan,
            $request->channel,
            $request->trigger,
            $request->idempotencyKey,
            $request->payload,
            $request->occurredAt(),
        );

        return $this->runService->advanceStart($run, $version, $request);
    }

    public function resume(string $runId, WorkflowResumeRequest $request): WorkflowRunResult
    {
        $run = $this->runs->loadWorkflowRun($runId);
        if ($run === null) {
            throw new RuntimeException('Workflow run not found.');
        }

        if (in_array((string) $run['status'], ['completed', 'failed'], true)) {
            return new WorkflowRunResult(
                runId: (string) $run['id'],
                status: (string) $run['status'],
                currentStepId: isset($run['current_step_key']) ? (string) $run['current_step_key'] : null,
                replayed: true,
                waitingUntil: isset($run['waiting_until']) && $run['waiting_until'] !== null
                    ? CarbonImmutable::parse((string) $run['waiting_until'])
                    : null,
                output: is_array($run['output'] ?? null) ? $run['output'] : [],
            );
        }

        $version = $this->runs->loadWorkflowVersion((string) $run['workflow_version_id']);
        if ($version === null) {
            throw new RuntimeException('Workflow version not found for run.');
        }

        return $this->runService->advanceResume($run, $version, $request);
    }
}
