<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\ToolPolicyEngineInterface;
use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Domain\Automation\ToolAuthorizationRequest;
use App\Domain\Automation\WorkflowResumeRequest;
use App\Domain\Automation\WorkflowRunResult;
use App\Domain\Automation\WorkflowStartRequest;
use Carbon\CarbonImmutable;
use RuntimeException;

final class WorkflowRunService
{
    /**
     * @param  array<string, callable>  $toolHandlers
     */
    public function __construct(
        private readonly WorkflowRepositoryInterface $repository,
        private readonly ToolPolicyEngineInterface $policyEngine,
        private readonly array $toolHandlers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $workflowVersion
     */
    public function advanceStart(array $run, array $workflowVersion, WorkflowStartRequest $request): WorkflowRunResult
    {
        return $this->advance($run, $workflowVersion, $request->actorId, $request->actorRoles, $request->plan, $request->channel, $request->confirmations, $request->occurredAt());
    }

    /**
     * Workforce actions enter only after the outer action executor has made
     * its policy, confirmation and action-level idempotency decisions.
     */
    public function advanceAgentAction(array $run, array $workflowVersion, WorkflowStartRequest $request): WorkflowRunResult
    {
        return $this->advance($run, $workflowVersion, $request->actorId, $request->actorRoles, $request->plan, $request->channel, $request->confirmations, $request->occurredAt());
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $workflowVersion
     */
    public function advanceResume(array $run, array $workflowVersion, WorkflowResumeRequest $request): WorkflowRunResult
    {
        return $this->advance($run, $workflowVersion, $request->actorId, $request->actorRoles, $request->plan, $request->channel, $request->confirmations, $request->occurredAt());
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $workflowVersion
     * @param  list<string>  $actorRoles
     * @param  array<string, array{confirmation_id?: string, confirmation_token?: string}>  $confirmations
     */
    private function advance(
        array $run,
        array $workflowVersion,
        string $actorId,
        array $actorRoles,
        string $plan,
        string $channel,
        array $confirmations,
        CarbonImmutable $occurredAt,
    ): WorkflowRunResult {
        $steps = (array) (($workflowVersion['definition']['steps'] ?? null) ?: []);
        $currentStepKey = $run['current_step_key'] ?? null;
        $stepIndex = $this->resolveStartingIndex($steps, is_string($currentStepKey) ? $currentStepKey : null);

        if (($run['status'] ?? null) === 'waiting' && $currentStepKey !== null) {
            $waitingUntil = isset($run['waiting_until']) && $run['waiting_until'] !== null
                ? CarbonImmutable::parse((string) $run['waiting_until'])
                : null;
            if ($waitingUntil !== null && $occurredAt->lt($waitingUntil)) {
                return $this->result(array_merge($run, ['waiting_until' => $waitingUntil->toIso8601String()]));
            }

            $currentStep = $steps[$stepIndex] ?? null;
            if (is_array($currentStep) && ($currentStep['type'] ?? null) === 'wait') {
                $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], (string) $currentStep['id'], [
                    'status' => 'completed',
                    'finished_at' => $occurredAt->toIso8601String(),
                ]);
                $stepIndex++;
            }
        }

        for ($index = $stepIndex; $index < count($steps); $index++) {
            $step = (array) $steps[$index];
            $stepKey = (string) ($step['id'] ?? "step-{$index}");
            $stepType = (string) ($step['type'] ?? 'tool');

            if ($stepType === 'wait') {
                $waitingUntil = $occurredAt->addSeconds((int) ($step['delay_seconds'] ?? 0));
                $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                    'step_type' => 'wait',
                    'status' => 'waiting',
                    'attempts' => 0,
                    'scheduled_for' => $waitingUntil->toIso8601String(),
                ]);
                $this->repository->updateWorkflowRun((string) $run['id'], [
                    'status' => 'waiting',
                    'current_step_key' => $stepKey,
                    'waiting_until' => $waitingUntil->toIso8601String(),
                ]);

                return $this->result([
                    ...$run,
                    'status' => 'waiting',
                    'current_step_key' => $stepKey,
                    'waiting_until' => $waitingUntil->toIso8601String(),
                ]);
            }

            $existingStep = $this->repository->loadWorkflowStep((string) $run['id'], $stepKey);
            $attempts = (int) ($existingStep['attempts'] ?? 0) + 1;
            $action = (string) ($step['action'] ?? $stepKey);
            [$provider, $tool] = $this->toolIdentity($step);
            $providerToolKey = $provider.':'.$tool;
            $attemptIdempotencyKey = sprintf(
                'workflow:%s:step:%s:attempt:%d:%s',
                (string) $run['id'],
                $stepKey,
                $attempts,
                $providerToolKey,
            );

            $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                'step_type' => 'tool',
                'action' => $action,
                'status' => 'running',
                'attempts' => $attempts,
                'started_at' => $existingStep['started_at'] ?? $occurredAt->toIso8601String(),
                'compensation_action' => $step['compensation_action'] ?? null,
            ]);

            $this->repository->updateWorkflowRun((string) $run['id'], [
                'status' => 'running',
                'current_step_key' => $stepKey,
                'waiting_until' => null,
            ]);

            $attempt = $this->repository->createWorkflowStepAttempt(
                runId: (string) $run['id'],
                tenantId: (string) $run['tenant_id'],
                stepKey: $stepKey,
                attemptNumber: $attempts,
                attemptKind: 'tool',
                providerToolKey: $providerToolKey,
                idempotencyKey: $attemptIdempotencyKey,
                startedAt: $occurredAt,
            );

            $decision = $this->authorizeStepIfRequired(
                run: $run,
                step: $step,
                actorId: $actorId,
                actorRoles: $actorRoles,
                plan: $plan,
                channel: $channel,
                confirmations: $confirmations,
                provider: $provider,
                idempotencyKey: $attemptIdempotencyKey,
            );
            if ($decision !== null && ! $decision->allowed) {
                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'failed',
                    'error_code' => $decision->code,
                    'finished_at' => $occurredAt->toIso8601String(),
                ]);
                $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $decision->code,
                ]);
                $this->repository->updateWorkflowRun((string) $run['id'], [
                    'status' => 'failed',
                    'current_step_key' => $stepKey,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $decision->code,
                ]);

                return $this->result([
                    ...$run,
                    'status' => 'failed',
                    'current_step_key' => $stepKey,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $decision->code,
                ]);
            }

            try {
                $handler = $this->toolHandlers[$action] ?? null;
                if (! is_callable($handler)) {
                    throw new RuntimeException('tool_handler_missing');
                }

                /** @var array{output?: array<string, mixed>, duration_seconds?: int} $response */
                $response = $handler();
                $durationSeconds = (int) ($response['duration_seconds'] ?? 0);
                $timeoutSeconds = (int) ($step['timeout_seconds'] ?? config('automation.default_timeout_seconds', 30));

                if ($durationSeconds > $timeoutSeconds) {
                    throw new RuntimeException('tool_timeout');
                }

                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'completed',
                    'finished_at' => $occurredAt->toIso8601String(),
                    'output' => $response['output'] ?? [],
                ]);
                $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                    'status' => 'completed',
                    'attempts' => $attempts,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'output' => $response['output'] ?? [],
                    'last_error_code' => null,
                ]);
            } catch (RuntimeException $exception) {
                $code = $this->errorCode($exception);
                $retryable = $this->isRetryable($exception);
                $retryLimit = (int) ($step['retry_limit'] ?? 0);
                $retryDelaySeconds = (int) ($step['retry_delay_seconds'] ?? 0);

                if ($retryable && $attempts <= $retryLimit) {
                    $waitingUntil = $occurredAt->addSeconds($retryDelaySeconds);
                    $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                        'action' => $action,
                        'status' => 'waiting',
                        'error_code' => $code,
                        'finished_at' => $occurredAt->toIso8601String(),
                    ]);
                    $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                        'status' => 'waiting',
                        'attempts' => $attempts,
                        'scheduled_for' => $waitingUntil->toIso8601String(),
                        'last_error_code' => $code,
                    ]);
                    $this->repository->updateWorkflowRun((string) $run['id'], [
                        'status' => 'waiting',
                        'current_step_key' => $stepKey,
                        'waiting_until' => $waitingUntil->toIso8601String(),
                        'last_error_code' => $code,
                    ]);

                    return $this->result([
                        ...$run,
                        'status' => 'waiting',
                        'current_step_key' => $stepKey,
                        'waiting_until' => $waitingUntil->toIso8601String(),
                        'last_error_code' => $code,
                    ]);
                }

                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'failed',
                    'error_code' => $code,
                    'finished_at' => $occurredAt->toIso8601String(),
                ]);
                $this->repository->saveWorkflowStep((string) $run['id'], (string) $run['tenant_id'], $stepKey, [
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $code,
                ]);
                $this->compensate((string) $run['id'], (string) $run['tenant_id'], $occurredAt);
                $this->repository->updateWorkflowRun((string) $run['id'], [
                    'status' => 'failed',
                    'current_step_key' => $stepKey,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $code,
                ]);

                return $this->result([
                    ...$run,
                    'status' => 'failed',
                    'current_step_key' => $stepKey,
                    'finished_at' => $occurredAt->toIso8601String(),
                    'last_error_code' => $code,
                ]);
            }
        }

        $this->repository->updateWorkflowRun((string) $run['id'], [
            'status' => 'completed',
            'current_step_key' => null,
            'waiting_until' => null,
            'finished_at' => $occurredAt->toIso8601String(),
            'last_error_code' => null,
        ]);

        return $this->result([
            ...$run,
            'status' => 'completed',
            'current_step_key' => null,
            'waiting_until' => null,
            'finished_at' => $occurredAt->toIso8601String(),
            'last_error_code' => null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function resolveStartingIndex(array $steps, ?string $currentStepKey): int
    {
        if ($currentStepKey === null) {
            return 0;
        }

        foreach ($steps as $index => $step) {
            if (($step['id'] ?? null) === $currentStepKey) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  array<string, mixed>  $step
     * @param  list<string>  $actorRoles
     * @param  array<string, array{confirmation_id?: string, confirmation_token?: string}>  $confirmations
     */
    private function authorizeStepIfRequired(
        array $run,
        array $step,
        string $actorId,
        array $actorRoles,
        string $plan,
        string $channel,
        array $confirmations,
        string $provider,
        string $idempotencyKey,
    ): ?\App\Domain\Automation\ToolAuthorizationDecision {
        $authorization = is_array($step['authorization'] ?? null) ? $step['authorization'] : null;
        if ($authorization === null) {
            return null;
        }

        $tool = (string) ($authorization['tool'] ?? ($step['action'] ?? $step['id'] ?? 'tool'));
        $confirmation = $confirmations[$tool] ?? [];

        return $this->policyEngine->authorize(new ToolAuthorizationRequest(
            tenantId: (string) $run['tenant_id'],
            actorId: $actorId,
            actorRoles: $actorRoles,
            plan: $plan,
            channel: $channel,
            tool: $tool,
            toolVersion: (string) ($authorization['tool_version'] ?? '2026-08-01'),
            risk: (string) ($authorization['risk'] ?? 'read'),
            payloadHash: hash('sha256', json_encode($authorization['payload'] ?? [], JSON_THROW_ON_ERROR)),
            idempotencyKey: $idempotencyKey,
            confirmationId: $confirmation['confirmation_id'] ?? null,
            confirmationToken: $confirmation['confirmation_token'] ?? null,
            provider: $provider,
        ));
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{0: string, 1: string}
     */
    private function toolIdentity(array $step): array
    {
        $authorization = is_array($step['authorization'] ?? null) ? $step['authorization'] : [];
        $tool = (string) ($authorization['tool'] ?? ($step['action'] ?? $step['id'] ?? 'tool'));
        $provider = (string) ($authorization['provider'] ?? ($step['provider'] ?? 'local'));

        return [$provider !== '' ? $provider : 'local', $tool !== '' ? $tool : 'tool'];
    }

    private function compensate(string $runId, string $tenantId, CarbonImmutable $occurredAt): void
    {
        foreach ($this->repository->listCompensableCompletedSteps($runId) as $step) {
            $action = (string) ($step['compensation_action'] ?? '');
            if ($action === '') {
                continue;
            }

            $compensationStepKey = (string) $step['step_key'].':compensation';
            $existingCompensation = $this->repository->loadWorkflowStep($runId, $compensationStepKey);
            $attemptNumber = (int) ($existingCompensation['attempts'] ?? 0) + 1;
            $providerToolKey = 'local:'.$action;
            $idempotencyKey = sprintf(
                'workflow:%s:step:%s:compensation:%d:%s',
                $runId,
                (string) $step['step_key'],
                $attemptNumber,
                $providerToolKey,
            );
            $attempt = $this->repository->createWorkflowStepAttempt(
                runId: $runId,
                tenantId: $tenantId,
                stepKey: (string) $step['step_key'],
                attemptNumber: $attemptNumber,
                attemptKind: 'compensation',
                providerToolKey: $providerToolKey,
                idempotencyKey: $idempotencyKey,
                startedAt: $occurredAt,
            );
            $handler = $this->toolHandlers[$action] ?? null;
            if (! is_callable($handler)) {
                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'failed',
                    'error_code' => 'compensation_handler_missing',
                    'finished_at' => $occurredAt->toIso8601String(),
                ]);
                continue;
            }

            try {
                /** @var array{output?: array<string, mixed>} $response */
                $response = $handler();

                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'completed',
                    'finished_at' => $occurredAt->toIso8601String(),
                    'output' => $response['output'] ?? [],
                ]);

                $this->repository->saveWorkflowStep($runId, $tenantId, $compensationStepKey, [
                    'step_type' => 'compensation',
                    'action' => $action,
                    'status' => 'completed',
                    'attempts' => $attemptNumber,
                    'started_at' => $occurredAt->toIso8601String(),
                    'finished_at' => $occurredAt->toIso8601String(),
                    'output' => $response['output'] ?? [],
                ]);
            } catch (RuntimeException $exception) {
                $this->repository->finishWorkflowStepAttempt((string) $attempt['id'], [
                    'action' => $action,
                    'status' => 'failed',
                    'error_code' => $this->errorCode($exception),
                    'finished_at' => $occurredAt->toIso8601String(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function result(array $run, bool $replayed = false): WorkflowRunResult
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

    private function isRetryable(RuntimeException $exception): bool
    {
        return str_starts_with($exception->getMessage(), 'retryable:');
    }

    private function errorCode(RuntimeException $exception): string
    {
        $message = $exception->getMessage();

        if (str_starts_with($message, 'retryable:')) {
            return substr($message, strlen('retryable:'));
        }

        return $message !== '' ? $message : 'tool_failed';
    }
}
