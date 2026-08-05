<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Evaluation\AiEvaluationDecision;
use App\Application\Ai\Evaluation\AiEvaluationRunner;
use App\Application\Ai\Evaluation\AiEvaluationSelectionResolver;
use App\Application\Ai\Evaluation\AiReleaseGate;
use App\Application\Ai\Evaluation\EvaluationMetrics;
use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\Commands\RevokeAiReleaseDecisionCommand;
use App\Application\Security\PrivilegedActionGuard;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformAiEvaluationController extends Controller
{
    public function __construct(
        private readonly AiEvaluationRunner $runner,
        private readonly AiEvaluationSelectionResolver $resolver,
        private readonly AiReleaseGate $releaseGate,
        private readonly AiRegistryLifecycle $lifecycle,
        private readonly PrivilegedActionGuard $guard,
    ) {}

    public function run(Request $request): JsonResponse
    {
        $actor = $this->platformActor($request);
        $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'evaluation_version_id' => ['required', 'uuid'],
            'agent_version_id' => ['required', 'uuid'],
            'prompt_version_id' => ['required', 'uuid'],
            'provider_version_id' => ['required', 'uuid'],
            'model_version_id' => ['required', 'uuid'],
            'artifact_sha' => ['required', 'string', 'regex:/^git:[a-f0-9]{16,64}$/'],
        ]);

        return $this->transition(function () use ($tenantId, $validated): JsonResponse {
            $owner = DB::table('ai_evaluation_versions')
                ->where('id', $validated['evaluation_version_id'])->value('tenant_id');
            if (! is_string($owner) || ! hash_equals($tenantId, $owner)) {
                throw new AuthorizationException('Cross-tenant evaluation access is restricted.');
            }
            $selection = $this->resolver->resolve(
                (string) $validated['evaluation_version_id'],
                (string) $validated['agent_version_id'],
                (string) $validated['provider_version_id'],
                (string) $validated['model_version_id'],
                (string) $validated['prompt_version_id'],
            );
            $decision = $this->runner->run(
                (string) $validated['evaluation_version_id'],
                $selection,
                (string) $validated['artifact_sha'],
            );

            return response()->json(['data' => $this->decisionPayload($decision)], 201);
        });
    }

    public function comparison(Request $request): JsonResponse
    {
        $actor = $this->platformActor($request);
        $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'baseline_run_id' => ['required', 'uuid'],
            'candidate_run_id' => ['required', 'uuid', 'different:baseline_run_id'],
        ]);

        return $this->transition(function () use ($tenantId, $validated): JsonResponse {
            $runs = DB::table('ai_evaluation_runs')->where('tenant_id', $tenantId)
                ->whereIn('id', [$validated['baseline_run_id'], $validated['candidate_run_id']])
                ->get()->keyBy(static fn (object $run): string => (string) $run->id);
            $baseline = $runs->get((string) $validated['baseline_run_id']);
            $candidate = $runs->get((string) $validated['candidate_run_id']);
            if (! is_object($baseline) || ! is_object($candidate)
                || ! in_array((string) $baseline->status, ['passed', 'failed'], true)
                || ! in_array((string) $candidate->status, ['passed', 'failed'], true)) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $baselineMetrics = EvaluationMetrics::fromArray($this->object($baseline->aggregate_metrics))->toArray();
            $candidateMetrics = EvaluationMetrics::fromArray($this->object($candidate->aggregate_metrics))->toArray();
            $deltas = [];
            foreach (array_slice($candidateMetrics, 0, 10, true) as $key => $value) {
                $deltas[$key] = round((float) $value - (float) $baselineMetrics[$key], 6);
            }
            $baselineCodes = $this->list($baseline->blocking_codes);
            $candidateCodes = $this->list($candidate->blocking_codes);

            return response()->json(['data' => [
                'baseline_run_id' => (string) $baseline->id,
                'candidate_run_id' => (string) $candidate->id,
                'metric_deltas' => $deltas,
                'blocking_regressions' => array_values(array_diff($candidateCodes, $baselineCodes)),
            ]]);
        });
    }

    public function decide(Request $request): JsonResponse
    {
        $actor = $this->platformActor($request);
        $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate(['evaluation_run_id' => ['required', 'uuid']]);

        return $this->transition(function () use ($actor, $tenantId, $validated): JsonResponse {
            $owner = DB::table('ai_evaluation_runs')->where('id', $validated['evaluation_run_id'])->value('tenant_id');
            if (! is_string($owner) || ! hash_equals($tenantId, $owner)) {
                throw new AuthorizationException('Cross-tenant evaluation access is restricted.');
            }
            $decisionId = $this->releaseGate->decide(
                (string) $validated['evaluation_run_id'],
                (string) $actor->getAuthIdentifier(),
            );

            return response()->json(['data' => ['decision_id' => $decisionId]], 201);
        });
    }

    public function revoke(Request $request, string $decision): JsonResponse
    {
        if (! Str::isUuid($decision)) {
            return response()->json(['code' => 'ai_evaluation_transition_denied'], 422);
        }

        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);

        return $this->transition(function () use ($actor, $decision, $reason, $tenantId): JsonResponse {
            $this->lifecycle->revokeReleaseDecision(new RevokeAiReleaseDecisionCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                decisionId: $decision,
                reason: $reason,
            ));

            return response()->json(['data' => ['decision_id' => $decision, 'revoked' => true]]);
        });
    }

    private function platformActor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            throw new AuthorizationException('Platform evaluation access is restricted.');
        }

        return $user;
    }

    private function platformTenantId(Request $request): string
    {
        $tenantId = config('ai.public.billing_tenant_id');
        if (! is_string($tenantId) || ! Str::isUuid($tenantId)
            || ! DB::table('tenants')->where('id', $tenantId)->exists()) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }
        $requested = $request->input('tenant_id');
        if (! is_string($requested) || ! Str::isUuid($requested)) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Provide the exact platform tenant UUID for this evaluation transition.',
            ]);
        }
        if (! hash_equals($tenantId, $requested)) {
            throw new AuthorizationException('Cross-tenant evaluation access is restricted.');
        }

        return $tenantId;
    }

    private function guardMutation(Request $request, User $actor): string
    {
        $password = $request->input('current_password');
        $credentialsVerified = is_string($password) && Hash::check($password, (string) $actor->getAuthPassword());
        $mfaRequired = (bool) config('security.admin_mfa_required', false);

        return $this->guard->authorizePlatformMutation(
            $actor->isSuperAdmin(),
            $credentialsVerified,
            ! $mfaRequired || $this->hasRecentMfaVerification($request, $actor),
            is_string($request->input('reason')) ? $request->input('reason') : '',
        );
    }

    private function hasRecentMfaVerification(Request $request, User $actor): bool
    {
        if (! $request->hasSession()) {
            return false;
        }
        $verification = $request->session()->get('auth.two_factor_verified');
        $maxAge = max(60, (int) config('security.impersonation.mfa_max_age_seconds', 600));

        return $actor->hasEnabledTwoFactorAuthentication()
            && is_array($verification)
            && hash_equals((string) $actor->getAuthIdentifier(), (string) ($verification['user_id'] ?? ''))
            && is_numeric($verification['verified_at'] ?? null)
            && (int) $verification['verified_at'] >= now()->getTimestamp() - $maxAge;
    }

    private function transition(callable $operation): JsonResponse
    {
        try {
            return $operation();
        } catch (AiExecutionDeniedException|ValidationException) {
            return response()->json(['code' => 'ai_evaluation_transition_denied'], 422);
        }
    }

    private function decisionPayload(AiEvaluationDecision $decision): array
    {
        return [
            'run_id' => $decision->runId,
            'status' => $decision->status,
            'metrics' => $decision->metrics->toArray(),
            'blockers' => $decision->blockingFailures,
        ];
    }

    /** @return array<string, mixed> */
    private function object(mixed $raw): array
    {
        $value = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($value) || array_is_list($value)) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        return $value;
    }

    /** @return list<string> */
    private function list(mixed $raw): array
    {
        $value = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($value) || ! array_is_list($value)
            || array_filter($value, static fn (mixed $item): bool => is_string($item)) !== $value) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        return $value;
    }
}
