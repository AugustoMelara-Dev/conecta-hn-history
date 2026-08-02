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
        return $this->startWorkflow($request, false);
    }

    /**
     * Dedicated entry point for a workforce action after its outer policy,
     * confirmation and idempotency checks have succeeded.
     */
    public function startAgentAction(WorkflowStartRequest $request): WorkflowRunResult
    {
        if ($request->trigger !== 'agent_action') {
            throw new RuntimeException('Agent action workflow trigger is required.');
        }

        return $this->startWorkflow($request, true);
    }

    private function startWorkflow(WorkflowStartRequest $request, bool $agentAction): WorkflowRunResult
    {
        $version = $this->definitions->publishedVersion($request->tenantId, $request->workflowKey, $request->workflowVersion);
        if ($version === null) {
            throw new RuntimeException('Published workflow version not found.');
        }

        $creation = $this->runs->findOrCreateWorkflowRun(
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
        $run = $creation['run'];

        if ($creation['replayed']) {
            if ((string) ($run['actor_id'] ?? '') !== $request->actorId
                || (string) ($run['plan'] ?? '') !== $request->plan
                || (string) ($run['channel'] ?? '') !== $request->channel
                || (array) ($run['payload'] ?? []) !== $request->payload) {
                throw new RuntimeException('Workflow start idempotency context conflict.');
            }

            return $this->resultFromRun($run, true);
        }

        return $agentAction
            ? $this->runService->advanceAgentAction($run, $version, $request)
            : $this->runService->advanceStart($run, $version, $request);
    }

    public function resume(string $runId, WorkflowResumeRequest $request): WorkflowRunResult
    {
        if ($request->tenantId === '') {
            throw new RuntimeException('Workflow run tenant authorization failed.');
        }

        $run = $this->runs->loadWorkflowRunForTenant($runId, $request->tenantId);
        if ($run === null) {
            throw new RuntimeException('Workflow run tenant authorization failed.');
        }

        if ((string) ($run['tenant_id'] ?? '') !== $request->tenantId) {
            throw new RuntimeException('Workflow run tenant authorization failed.');
        }

        if ($request->actorId === '' || (string) ($run['actor_id'] ?? '') !== $request->actorId) {
            throw new RuntimeException('Workflow run actor authorization failed.');
        }

        if ((string) ($run['plan'] ?? '') !== $request->plan
            || (string) ($run['channel'] ?? '') !== $request->channel) {
            throw new RuntimeException('Workflow run execution context mismatch.');
        }

        if (in_array((string) $run['status'], ['completed', 'failed'], true)) {
            return $this->resultFromRun($run, true);
        }

        $version = $this->runs->loadWorkflowVersionForTenant(
            (string) $run['workflow_version_id'],
            $request->tenantId,
        );
        if ($version === null) {
            throw new RuntimeException('Workflow version not found for run.');
        }

        return $this->runService->advanceResume($run, $version, $request);
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function resultFromRun(array $run, bool $replayed): WorkflowRunResult
    {
        return new WorkflowRunResult(
            runId: (string) $run['id'],
            status: (string) $run['status'],
            currentStepId: isset($run['current_step_key']) ? (string) $run['current_step_key'] : null,
            replayed: $replayed,
            waitingUntil: isset($run['waiting_until']) && $run['waiting_until'] !== null
                ? CarbonImmutable::parse((string) $run['waiting_until'])
                : null,
            output: is_array($run['output'] ?? null) ? $run['output'] : [],
        );
    }
}
