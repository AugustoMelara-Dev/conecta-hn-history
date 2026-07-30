<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\CreateAiDraftCommand;
use App\Application\Ai\Registry\Commands\KillSwitchAiVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
use App\Application\Ai\Registry\Commands\RollbackAgentActivationCommand;
use App\Application\Security\PrivilegedActionGuard;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformAiRegistryController extends Controller
{
    public function __construct(
        private readonly AiRegistryLifecycle $lifecycle,
        private readonly PrivilegedActionGuard $guard,
    ) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $this->platformActor($request);
        $tenantId = $this->platformTenantId($request);
        $query = $this->readQuery($resource, $tenantId);
        $perPage = min(100, max(1, $request->integer('per_page', 25)));
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function createDraft(Request $request): JsonResponse
    {
        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'version_type' => ['required', 'string', 'in:provider,model,agent,prompt'],
            'definition_id' => ['required', 'uuid'],
            'payload' => ['required', 'array'],
        ]);

        return $this->transition(function () use (
            $actor,
            $reason,
            $tenantId,
            $validated,
        ): JsonResponse {
            $versionType = (string) $validated['version_type'];
            $versionId = $this->lifecycle->createDraft(new CreateAiDraftCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                versionType: $versionType,
                definitionId: (string) $validated['definition_id'],
                payload: $validated['payload'],
                reason: $reason,
            ));

            return response()->json([
                'data' => [
                    'id' => $versionId,
                    'version_type' => $versionType,
                    'lifecycle_status' => 'draft',
                ],
            ], 201);
        });
    }

    public function promote(
        Request $request,
        string $versionType,
        string $versionId,
    ): JsonResponse {
        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'target_status' => ['required', 'string', 'in:candidate,eligible,active,retired'],
            'release_decision_id' => ['nullable', 'uuid'],
        ]);

        return $this->transition(function () use (
            $actor,
            $reason,
            $tenantId,
            $validated,
            $versionId,
            $versionType,
        ): JsonResponse {
            $this->lifecycle->promote(new PromoteAiVersionCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                versionType: $versionType,
                versionId: $versionId,
                targetStatus: (string) $validated['target_status'],
                releaseDecisionId: isset($validated['release_decision_id'])
                    ? (string) $validated['release_decision_id']
                    : null,
                reason: $reason,
            ));

            return response()->json([
                'data' => [
                    'id' => $versionId,
                    'version_type' => $versionType,
                    'lifecycle_status' => (string) $validated['target_status'],
                ],
            ]);
        });
    }

    public function activate(Request $request): JsonResponse
    {
        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'scope_type' => ['required', 'string', 'max:32'],
            'scope_key' => ['required', 'string', 'max:190'],
            'agent_version_id' => ['required', 'uuid'],
            'release_decision_id' => ['required', 'uuid'],
            'rollout_percentage' => ['required', 'integer', 'between:0,100'],
            'expected_activation_id' => ['nullable', 'uuid'],
            'expected_lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->transition(function () use (
            $actor,
            $reason,
            $tenantId,
            $validated,
        ): JsonResponse {
            $activationId = $this->lifecycle->activate(new ActivateAgentVersionCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                scopeType: (string) $validated['scope_type'],
                scopeKey: (string) $validated['scope_key'],
                agentVersionId: (string) $validated['agent_version_id'],
                releaseDecisionId: (string) $validated['release_decision_id'],
                rolloutPercentage: (int) $validated['rollout_percentage'],
                reason: $reason,
                expectedActivationId: isset($validated['expected_activation_id'])
                    ? (string) $validated['expected_activation_id']
                    : null,
                expectedLockVersion: isset($validated['expected_lock_version'])
                    ? (int) $validated['expected_lock_version']
                    : null,
            ));

            return response()->json(['data' => ['id' => $activationId]], 201);
        });
    }

    public function rollback(Request $request, string $activationId): JsonResponse
    {
        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);
        $validated = $request->validate([
            'expected_lock_version' => ['required', 'integer', 'min:0'],
        ]);

        return $this->transition(function () use (
            $activationId,
            $actor,
            $reason,
            $tenantId,
            $validated,
        ): JsonResponse {
            $rollbackId = $this->lifecycle->rollback(new RollbackAgentActivationCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                activationId: $activationId,
                expectedLockVersion: (int) $validated['expected_lock_version'],
                reason: $reason,
            ));

            return response()->json(['data' => ['id' => $rollbackId]], 201);
        });
    }

    public function killSwitch(
        Request $request,
        string $versionType,
        string $versionId,
    ): JsonResponse {
        $actor = $this->platformActor($request);
        $reason = $this->guardMutation($request, $actor);
        $tenantId = $this->platformTenantId($request);

        return $this->transition(function () use (
            $actor,
            $reason,
            $tenantId,
            $versionId,
            $versionType,
        ): JsonResponse {
            $this->lifecycle->killSwitch(new KillSwitchAiVersionCommand(
                actorId: (string) $actor->getKey(),
                tenantId: $tenantId,
                versionType: $versionType,
                versionId: $versionId,
                reason: $reason,
            ));

            return response()->json([
                'data' => [
                    'id' => $versionId,
                    'version_type' => $versionType,
                    'lifecycle_status' => 'blocked',
                ],
            ]);
        });
    }

    private function readQuery(string $resource, string $tenantId): Builder
    {
        return match ($resource) {
            'providers' => DB::table('ai_provider_versions as versions')
                ->join('ai_providers as providers', 'providers.id', '=', 'versions.provider_id')
                ->select([
                    'versions.id',
                    'versions.provider_id',
                    'providers.provider_key',
                    'providers.adapter_key',
                    'providers.name',
                    'versions.version',
                    'versions.lifecycle_status',
                    'versions.kill_switch_enabled',
                    'versions.released_at',
                    'versions.blocked_at',
                    'versions.created_at',
                ])
                ->orderByDesc('versions.created_at')
                ->orderByDesc('versions.id'),
            'models' => DB::table('ai_model_versions as versions')
                ->join('ai_models as models', 'models.id', '=', 'versions.model_id')
                ->join('ai_providers as providers', 'providers.id', '=', 'models.provider_id')
                ->select([
                    'versions.id',
                    'versions.model_id',
                    'versions.provider_version_id',
                    'providers.provider_key',
                    'models.key',
                    'models.name',
                    'versions.version',
                    'versions.context_window',
                    'versions.input_cost_minor',
                    'versions.output_cost_minor',
                    'versions.currency',
                    'versions.lifecycle_status',
                    'versions.kill_switch_enabled',
                    'versions.released_at',
                    'versions.blocked_at',
                    'versions.created_at',
                ])
                ->orderByDesc('versions.created_at')
                ->orderByDesc('versions.id'),
            'agents' => DB::table('ai_agent_versions as versions')
                ->join('ai_agent_definitions as definitions', function ($join): void {
                    $join->on('definitions.tenant_id', '=', 'versions.tenant_id')
                        ->on('definitions.id', '=', 'versions.agent_definition_id');
                })
                ->where('versions.tenant_id', $tenantId)
                ->select([
                    'versions.id',
                    'versions.tenant_id',
                    'versions.agent_definition_id',
                    'versions.model_version_id',
                    'definitions.key',
                    'definitions.name',
                    'definitions.visibility',
                    'versions.version',
                    'versions.lifecycle_status',
                    'versions.kill_switch_enabled',
                    'versions.released_at',
                    'versions.blocked_at',
                    'versions.created_at',
                ])
                ->orderByDesc('versions.created_at')
                ->orderByDesc('versions.id'),
            'prompts' => DB::table('ai_prompt_versions as versions')
                ->join('ai_prompt_definitions as definitions', function ($join): void {
                    $join->on('definitions.tenant_id', '=', 'versions.tenant_id')
                        ->on('definitions.id', '=', 'versions.prompt_definition_id');
                })
                ->where('versions.tenant_id', $tenantId)
                ->select([
                    'versions.id',
                    'versions.tenant_id',
                    'versions.prompt_definition_id',
                    'definitions.key',
                    'definitions.name',
                    'definitions.visibility',
                    'versions.version',
                    'versions.lifecycle_status',
                    'versions.kill_switch_enabled',
                    'versions.released_at',
                    'versions.blocked_at',
                    'versions.created_at',
                ])
                ->orderByDesc('versions.created_at')
                ->orderByDesc('versions.id'),
            'activations' => DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->select([
                    'id',
                    'tenant_id',
                    'scope_type',
                    'scope_key',
                    'agent_version_id',
                    'rollout_percentage',
                    'release_decision_id',
                    'previous_activation_id',
                    'effective_from',
                    'effective_until',
                    'activated_by',
                    'lock_version',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            'decisions' => DB::table('ai_release_decisions')
                ->where('tenant_id', $tenantId)
                ->select([
                    'id',
                    'tenant_id',
                    'release_policy_id',
                    'evaluation_run_id',
                    'agent_version_id',
                    'decision',
                    'decided_by',
                    'decided_at',
                    'valid_from',
                    'valid_until',
                    'revoked_at',
                    'revoked_by',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            default => throw new AuthorizationException('Registry resource is not available.'),
        };
    }

    private function platformActor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            throw new AuthorizationException('Platform registry access is restricted.');
        }

        return $user;
    }

    private function platformTenantId(Request $request): string
    {
        $tenantId = config('ai.public.billing_tenant_id');
        if (! is_string($tenantId)
            || ! Str::isUuid($tenantId)
            || ! DB::table('tenants')->where('id', $tenantId)->exists()) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $requested = $request->input('tenant_id');
        if ($requested !== null
            && (! is_string($requested) || ! hash_equals($tenantId, $requested))) {
            throw new AuthorizationException('Cross-tenant registry access is restricted.');
        }

        return $tenantId;
    }

    private function guardMutation(Request $request, User $actor): string
    {
        $password = $request->input('current_password');
        $credentialsVerified = is_string($password)
            && Hash::check($password, (string) $actor->getAuthPassword());
        $mfaRequired = (bool) config('security.admin_mfa_required', false);

        return $this->guard->authorizePlatformMutation(
            isSuperAdmin: $actor->isSuperAdmin(),
            credentialsVerified: $credentialsVerified,
            mfaVerified: ! $mfaRequired || $this->hasRecentMfaVerification($request, $actor),
            reason: is_string($request->input('reason')) ? $request->input('reason') : '',
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
            && hash_equals(
                (string) $actor->getAuthIdentifier(),
                (string) ($verification['user_id'] ?? ''),
            )
            && is_numeric($verification['verified_at'] ?? null)
            && (int) $verification['verified_at'] >= now()->getTimestamp() - $maxAge;
    }

    /** @param callable(): JsonResponse $operation */
    private function transition(callable $operation): JsonResponse
    {
        try {
            return $operation();
        } catch (AiExecutionDeniedException|ValidationException) {
            return response()->json([
                'code' => 'ai_registry_transition_denied',
            ], 422);
        }
    }
}
