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
            $currentStep = $steps[$stepIndex] ?? null;
            if (is_array($currentStep) && ($currentStep['type'] ?? null) === 'wait') {
                $waitingUntil = CarbonImmutable::parse((string) $run['waiting_until']);
                if ($occurredAt->lt($waitingUntil)) {
                    return $this->result(array_merge($run, ['waiting_until' => $waitingUntil->toIso8601String()]));
                }

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

            $decision = $this->authorizeStepIfRequired($run, $step, $actorId, $actorRoles, $plan, $channel, $confirmations);
            if ($decision !== null && ! $decision->allowed) {
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
            idempotencyKey: (string) $run['id'].':'.$tool,
            confirmationId: $confirmation['confirmation_id'] ?? null,
            confirmationToken: $confirmation['confirmation_token'] ?? null,
        ));
    }

    private function compensate(string $runId, string $tenantId, CarbonImmutable $occurredAt): void
    {
        foreach ($this->repository->listCompensableCompletedSteps($runId) as $step) {
            $action = (string) ($step['compensation_action'] ?? '');
            if ($action === '') {
                continue;
            }

            $handler = $this->toolHandlers[$action] ?? null;
            if (! is_callable($handler)) {
                continue;
            }

            /** @var array{output?: array<string, mixed>} $response */
            $response = $handler();

            $this->repository->saveWorkflowStep($runId, $tenantId, (string) $step['step_key'].':compensation', [
                'step_type' => 'compensation',
                'action' => $action,
                'status' => 'completed',
                'attempts' => 1,
                'started_at' => $occurredAt->toIso8601String(),
                'finished_at' => $occurredAt->toIso8601String(),
                'output' => $response['output'] ?? [],
            ]);
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
