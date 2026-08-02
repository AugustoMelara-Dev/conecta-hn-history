<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\ToolPolicyEngineInterface;
use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use App\Domain\Automation\ToolAuthorizationDecision;
use App\Domain\Automation\ToolAuthorizationRequest;
use Carbon\CarbonImmutable;

final readonly class ToolPolicyEngine implements ToolPolicyEngineInterface
{
    public function __construct(
        private WorkflowRepositoryInterface $repository,
    ) {}

    public function authorize(ToolAuthorizationRequest $request): ToolAuthorizationDecision
    {
        $policy = $this->repository->findToolPolicy($request->tenantId, $request->tool, $request->toolVersion);
        if ($policy === null) {
            return ToolAuthorizationDecision::deny('policy_missing');
        }

        if (! $this->contains((array) ($policy['allowed_roles'] ?? []), $request->actorRoles)) {
            return ToolAuthorizationDecision::deny('role_denied');
        }

        if (! in_array($request->plan, (array) ($policy['allowed_plans'] ?? []), true)) {
            return ToolAuthorizationDecision::deny('plan_denied');
        }

        if (! in_array($request->channel, (array) ($policy['allowed_channels'] ?? []), true)) {
            return ToolAuthorizationDecision::deny('channel_denied');
        }

        if ($this->riskRank($request->risk) > $this->riskRank((string) ($policy['max_risk'] ?? 'read'))) {
            return ToolAuthorizationDecision::deny('risk_denied');
        }

        $occurredAt = CarbonImmutable::now();

        return $this->repository->connection()->transaction(function () use ($request, $policy, $occurredAt): ToolAuthorizationDecision {
            $scope = 'tool:'.$request->tool;

            if ($this->repository->hasIdempotencyClaim($request->tenantId, $scope, $request->idempotencyKey)) {
                return ToolAuthorizationDecision::deny('idempotency_replay');
            }

            if ($this->requiresConfirmation($request->risk, (bool) ($policy['confirmation_required'] ?? false))) {
                if ($request->confirmationId === null || $request->confirmationToken === null) {
                    return ToolAuthorizationDecision::deny('confirmation_required');
                }

                $attempt = $this->repository->lockConfirmationAttempt($request->confirmationId);
                if ($attempt === null
                    || (string) ($attempt['tenant_id'] ?? '') !== $request->tenantId
                    || (string) ($attempt['actor_id'] ?? '') !== $request->actorId
                    || (string) ($attempt['tool'] ?? '') !== $request->tool
                    || (string) ($attempt['tool_version'] ?? '') !== $request->toolVersion
                    || (string) ($attempt['payload_hash'] ?? '') !== $request->payloadHash) {
                    return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
                }

                $status = (string) ($attempt['status'] ?? 'pending');
                $expiresAt = CarbonImmutable::parse((string) $attempt['expires_at']);
                if ($status !== 'pending' || $expiresAt->lte($occurredAt)) {
                    $this->repository->updateConfirmationAttempt($request->confirmationId, 'expired', $occurredAt);

                    return ToolAuthorizationDecision::deny('confirmation_expired');
                }

                if (! hash_equals(
                    (string) ($attempt['confirmation_token_hash'] ?? ''),
                    hash('sha256', $request->confirmationToken),
                )) {
                    return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
                }

                $this->repository->updateConfirmationAttempt($request->confirmationId, 'consumed', $occurredAt);
            }

            if (! $this->repository->claimIdempotency(
                $request->tenantId,
                $scope,
                $request->idempotencyKey,
                $request->payloadHash,
                $occurredAt,
            )) {
                return ToolAuthorizationDecision::deny('idempotency_replay');
            }

            return ToolAuthorizationDecision::allow('authorized');
        }, 3);
    }

    /**
     * @param  list<string>  $haystack
     * @param  list<string>  $needles
     */
    private function contains(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, true)) {
                return true;
            }
        }

        return false;
    }

    private function requiresConfirmation(string $risk, bool $policyRequiresConfirmation): bool
    {
        return $policyRequiresConfirmation || $this->riskRank($risk) >= $this->riskRank('mutation');
    }

    private function riskRank(string $risk): int
    {
        return match ($risk) {
            'read' => 0,
            'prepare' => 1,
            'mutation' => 2,
            'economic' => 3,
            default => 99,
        };
    }
}
