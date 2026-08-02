<?php

declare(strict_types=1);

namespace App\Application\Automation\AgentAction;

use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Automation\ToolAuthorizationRequest;
use App\Domain\Automation\WorkflowStartRequest;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\AI\Tools\AiToolConfirmationService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class AgentActionExecutor
{
    public function __construct(
        private ToolPolicyEngine $policyEngine,
        private AiToolConfirmationService $confirmations,
        private WorkflowRuntime $workflowRuntime,
        private DatabaseWorkflowRepository $repository,
        private TenantContext $tenantContext,
    ) {}

    public function execute(AgentActionRequest $request): AgentActionResult
    {
        $activeTenantId = $this->activeTenantId();
        if ($activeTenantId === null || ! hash_equals($activeTenantId, $request->tenantId)) {
            return $this->blocked(
                request: $request,
                auditTenantId: $activeTenantId ?? $request->tenantId,
                code: $activeTenantId === null ? 'tenant_context_required' : 'tenant_context_mismatch',
            );
        }

        $policy = $this->policyEngine->evaluate(new ToolAuthorizationRequest(
            tenantId: $request->tenantId,
            actorId: $request->agentVersionId,
            actorRoles: ['agent'],
            plan: 'pro',
            channel: 'workforce',
            tool: $request->actionKey,
            toolVersion: 'agent-action-v1',
            risk: $request->risk,
            payloadHash: $request->payloadHash,
            idempotencyKey: $request->idempotencyKey,
            confirmationId: null,
            confirmationToken: null,
            provider: 'workflow',
        ));
        if (! $policy->allowed) {
            return $this->blocked($request, $request->tenantId, $policy->code);
        }

        $confirmation = $this->confirmations->authorizeAgentAction(
            tenantId: $request->tenantId,
            actionKey: $request->actionKey,
            payloadHash: $request->payloadHash,
            confirmationId: $request->confirmationId,
            confirmationToken: $request->confirmationToken,
            required: (bool) ($policy->metadata['confirmation_required'] ?? false),
        );
        if (! $confirmation->allowed) {
            return $this->blocked($request, $request->tenantId, $confirmation->code, $request->confirmationId);
        }

        $audit = $this->repository->connection()->transaction(function () use ($request): ?AgentActionAudit {
            if (! $this->repository->claimAgentActionIdempotency(
                $request->tenantId,
                $request->actionKey,
                $request->idempotencyKey,
                $request->payloadHash,
                CarbonImmutable::now(),
            )) {
                return null;
            }

            return $this->createAudit($request, $request->tenantId, 'policy_authorized', 'authorized', $request->confirmationId);
        }, 3);
        if ($audit === null) {
            $existing = $this->repository->findAgentActionAuditByIdempotency(
                $request->tenantId,
                $request->actionKey,
                $request->idempotencyKey,
            );
            if ($existing === null) {
                throw new RuntimeException('Agent action idempotency audit is missing.');
            }

            return new AgentActionResult(false, 'duplicate', 'idempotency_replay', (string) ($existing['workflow_run_id'] ?? '') ?: null, true, $this->auditFromRow($existing));
        }

        try {
            $workflow = $this->workflowRuntime->startAgentAction(new WorkflowStartRequest(
                tenantId: $request->tenantId,
                workflowKey: $request->actionKey,
                workflowVersion: $request->workflowVersion,
                actorId: $request->agentVersionId,
                actorRoles: ['agent'],
                plan: 'pro',
                channel: 'workforce',
                idempotencyKey: 'agent-action:'.$request->idempotencyKey,
                trigger: 'agent_action',
                payload: $request->input,
            ));
            $executed = $workflow->status === 'completed';
            $run = $this->repository->loadWorkflowRunForTenant($workflow->runId, $request->tenantId);
            $reasonCode = $executed
                ? 'completed'
                : ($workflow->status === 'failed'
                    ? (string) ($run['last_error_code'] ?? 'workflow_failed')
                    : $workflow->status);
            $this->repository->updateAgentActionAudit($audit->id, [
                'workflow_run_id' => $workflow->runId,
                'status' => $executed ? 'completed' : $workflow->status,
                'policy_code' => $reasonCode,
                'metadata' => AgentActionAudit::sanitize(['workflow_status' => $workflow->status]),
                'updated_at' => now(),
            ]);

            return new AgentActionResult(
                $executed,
                $executed ? 'completed' : $workflow->status,
                $reasonCode,
                $workflow->runId,
                $workflow->replayed,
                new AgentActionAudit(
                    $audit->id,
                    $audit->tenantId,
                    $audit->agentVersionId,
                    $audit->conversationId,
                    $audit->actionKey,
                    $reasonCode,
                    $audit->confirmationId,
                    $workflow->runId,
                    null,
                    $audit->occurredAt,
                    ['workflow_status' => $workflow->status],
                ),
            );
        } catch (RuntimeException) {
            $this->repository->updateAgentActionAudit($audit->id, [
                'status' => 'failed',
                'policy_code' => 'workflow_execution_failed',
                'updated_at' => now(),
            ]);

            return new AgentActionResult(false, 'failed', 'workflow_execution_failed', null, false, $audit);
        }
    }

    private function activeTenantId(): ?string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            return null;
        }
        try {
            return strtolower($this->tenantContext->id());
        } catch (\Throwable) {
            return null;
        }
    }

    private function blocked(AgentActionRequest $request, string $auditTenantId, string $code, ?string $confirmationId = null): AgentActionResult
    {
        $existing = $this->repository->findAgentActionAuditByIdempotency($auditTenantId, $request->actionKey, $request->idempotencyKey);
        $audit = $existing === null
            ? $this->createAudit($request, $auditTenantId, $code, 'blocked', $confirmationId)
            : $this->auditFromRow($existing);

        return new AgentActionResult(false, 'blocked', $code, null, false, $audit);
    }

    private function createAudit(AgentActionRequest $request, string $auditTenantId, string $policyCode, string $status, ?string $confirmationId): AgentActionAudit
    {
        $audit = new AgentActionAudit(
            id: (string) Str::uuid(),
            tenantId: $auditTenantId,
            agentVersionId: $request->agentVersionId,
            conversationId: $request->conversationId,
            actionKey: $request->actionKey,
            policyCode: $policyCode,
            confirmationId: $confirmationId,
            workflowRunId: null,
            outcomeId: null,
            occurredAt: CarbonImmutable::now(),
            metadata: ['input_hash' => $request->payloadHash, 'risk' => $request->risk],
        );
        $this->repository->createAgentActionAudit([
            'id' => $audit->id,
            'tenant_id' => $audit->tenantId,
            'agent_version_id' => $audit->agentVersionId,
            'conversation_id' => $audit->conversationId,
            'action_key' => $audit->actionKey,
            'idempotency_key' => $request->idempotencyKey,
            'input_hash' => $request->payloadHash,
            'risk' => $request->risk,
            'policy_code' => $audit->policyCode,
            'confirmation_id' => $audit->confirmationId,
            'status' => $status,
            'metadata' => $audit->metadata,
            'created_at' => $audit->occurredAt,
            'updated_at' => $audit->occurredAt,
        ]);

        return $audit;
    }

    /** @param array<string, mixed> $row */
    private function auditFromRow(array $row): AgentActionAudit
    {
        return new AgentActionAudit(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            agentVersionId: (string) $row['agent_version_id'],
            conversationId: (string) $row['conversation_id'],
            actionKey: (string) $row['action_key'],
            policyCode: (string) $row['policy_code'],
            confirmationId: isset($row['confirmation_id']) ? (string) $row['confirmation_id'] : null,
            workflowRunId: isset($row['workflow_run_id']) ? (string) $row['workflow_run_id'] : null,
            outcomeId: isset($row['outcome_id']) ? (string) $row['outcome_id'] : null,
            occurredAt: CarbonImmutable::parse((string) $row['created_at']),
            metadata: is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
        );
    }
}
