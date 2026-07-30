<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\CreateAiDraftCommand;
use App\Application\Ai\Registry\Commands\KillSwitchAiVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
use App\Application\Ai\Registry\Commands\RevokeAiReleaseDecisionCommand;
use App\Application\Ai\Registry\Commands\RollbackAgentActivationCommand;
use App\Domain\Ai\Events\AiRegistryChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AiRegistryLifecycle
{
    /** @var array<string, array{table:string,tenant_scoped:bool}> */
    private const VERSION_TYPES = [
        'provider' => ['table' => 'ai_provider_versions', 'tenant_scoped' => false],
        'model' => ['table' => 'ai_model_versions', 'tenant_scoped' => false],
        'agent' => ['table' => 'ai_agent_versions', 'tenant_scoped' => true],
        'prompt' => ['table' => 'ai_prompt_versions', 'tenant_scoped' => true],
    ];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['candidate'],
        'candidate' => ['eligible'],
        'eligible' => ['active', 'retired'],
        'active' => ['retired'],
        'retired' => [],
        'blocked' => [],
    ];

    public function __construct(
        private OutboxRepositoryInterface $outbox,
        private AiRegistryChecksum $checksum,
        private LaravelAiContentCipher $cipher,
        private AiReleaseEligibility $eligibility,
        private AiReleaseCompatibility $compatibility,
        private AiRegistrySecretPolicy $secretPolicy,
        private AiRegistryLockCoordinator $locks,
        private AiRegistryRetirementGuard $retirement,
    ) {}

    public function createDraft(CreateAiDraftCommand $command): string
    {
        $this->assertCommandIds(
            $command->actorId,
            $command->tenantId,
            $command->definitionId,
        );
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);
        $this->versionType($command->versionType);

        return DB::transaction(function () use (
            $actor,
            $command,
            $tenantId,
            $reason,
        ): string {
            $this->locks->tenant($tenantId);
            [$table, $ownerColumn, $owner] = $this->draftOwner(
                $command->versionType,
                $command->definitionId,
                $tenantId,
            );
            $checksum = $this->checksum->for([
                'version_type' => $command->versionType,
                'definition_id' => $command->definitionId,
                'payload' => $this->checksumPayload(
                    $command->versionType,
                    $command->payload,
                ),
            ]);
            $existing = DB::table($table)
                ->where($ownerColumn, $command->definitionId)
                ->where('checksum', $checksum)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (hash_equals('draft', (string) $existing->lifecycle_status)
                    && ! (bool) $existing->kill_switch_enabled) {
                    return (string) $existing->id;
                }

                throw new AiExecutionDeniedException('registry_conflict');
            }
            $versionId = (string) Str::uuid();
            $version = ((int) DB::table($table)
                ->where($ownerColumn, $command->definitionId)
                ->max('version')) + 1;
            $payload = $this->draftPayload(
                $command->versionType,
                $command->payload,
                $tenantId,
                $versionId,
                $owner,
            );
            $timestamps = [
                'id' => $versionId,
                $ownerColumn => $command->definitionId,
                'version' => $version,
                'checksum' => $checksum,
                'released_at' => null,
                'lifecycle_status' => 'draft',
                'kill_switch_enabled' => false,
                'blocked_at' => null,
                'blocked_by' => null,
                'blocked_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (in_array($command->versionType, ['agent', 'prompt'], true)) {
                $timestamps['tenant_id'] = $tenantId;
            }

            DB::table($table)->insert([...$timestamps, ...$payload]);

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $versionId,
                eventName: 'ai_registry.draft_created',
                reason: $reason,
                attributes: [
                    'version_type' => $command->versionType,
                    'definition_id' => $command->definitionId,
                    'lifecycle_status' => 'draft',
                ],
            );

            return $versionId;
        }, 3);
    }

    public function promote(PromoteAiVersionCommand $command): void
    {
        $this->assertCommandIds($command->actorId, $command->tenantId, $command->versionId);
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);
        $type = $this->versionType($command->versionType);
        if (! in_array($command->targetStatus, [
            'candidate',
            'eligible',
            'active',
            'retired',
        ], true)) {
            $this->invalid('target_status', 'Unsupported registry lifecycle target.');
        }
        if ($command->versionType === 'agent' && $command->targetStatus === 'active') {
            $this->invalid('target_status', 'Agent versions become active only through an activation.');
        }

        DB::transaction(function () use (
            $actor,
            $command,
            $tenantId,
            $reason,
            $type,
        ): void {
            $this->locks->tenant($tenantId);
            $set = null;
            if ($command->targetStatus === 'eligible') {
                if (! is_string($command->releaseDecisionId)
                    || ! Str::isUuid($command->releaseDecisionId)) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
                $set = $this->locks->releaseGraph(
                    tenantId: $tenantId,
                    decisionId: $command->releaseDecisionId,
                    allowedLifecycleStatuses: ['candidate', 'eligible', 'active'],
                    denialReason: 'evaluation_gate',
                );
                if (! hash_equals(
                    $command->versionId,
                    $set->versionId($command->versionType),
                )) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
            }
            $version = $this->version(
                $type['table'],
                $type['tenant_scoped'],
                $tenantId,
                $command->versionId,
                true,
            );
            $from = (string) $version->lifecycle_status;
            if (! in_array($command->targetStatus, self::TRANSITIONS[$from] ?? [], true)) {
                $this->invalid(
                    'target_status',
                    "Registry version cannot transition from {$from} to {$command->targetStatus}.",
                );
            }
            if ((bool) $version->kill_switch_enabled || $from === 'blocked') {
                throw new AiExecutionDeniedException('kill_switch');
            }
            if ($command->targetStatus === 'candidate') {
                $this->assertCandidateDependencies(
                    $command->versionType,
                    $version,
                    $tenantId,
                );
            }

            if ($command->targetStatus === 'eligible') {
                if (! $set instanceof AiReleaseCandidateSet) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
                $this->compatibility->assertIntrinsic($set, 'evaluation_gate');
                $this->promoteCandidateSet($tenantId, $set);
            } elseif ($command->releaseDecisionId !== null) {
                $this->invalid(
                    'release_decision_id',
                    'A release decision is accepted only for eligibility promotion.',
                );
            }

            if ($command->targetStatus === 'retired') {
                $this->retirement->assertNotReferenced(
                    $tenantId,
                    $command->versionType,
                    $command->versionId,
                );
            }

            $attributes = [
                'lifecycle_status' => $command->targetStatus,
                'updated_at' => now(),
            ];
            if ($command->targetStatus === 'candidate' && $version->released_at === null) {
                $attributes['released_at'] = now();
            }

            $updated = $command->targetStatus === 'eligible'
                ? 1
                : DB::table($type['table'])
                    ->where('id', $command->versionId)
                    ->where('lifecycle_status', $from)
                    ->where('kill_switch_enabled', false)
                    ->update($attributes);
            if ($updated !== 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $command->versionId,
                eventName: 'ai_registry.version_promoted',
                reason: $reason,
                attributes: [
                    'version_type' => $command->versionType,
                    'from_status' => $from,
                    'to_status' => $command->targetStatus,
                    'release_decision_id' => $command->releaseDecisionId,
                ],
            );
        }, 3);
    }

    public function activate(ActivateAgentVersionCommand $command): string
    {
        $this->assertCommandIds(
            $command->actorId,
            $command->tenantId,
            $command->agentVersionId,
            $command->releaseDecisionId,
        );
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);
        $this->assertScope($tenantId, $command->scopeType, $command->scopeKey);
        if ($command->rolloutPercentage < 0 || $command->rolloutPercentage > 100) {
            $this->invalid('rollout_percentage', 'Rollout percentage must be between 0 and 100.');
        }
        if (($command->expectedActivationId === null) !== ($command->expectedLockVersion === null)
            || ($command->expectedActivationId !== null
                && ! Str::isUuid($command->expectedActivationId))
            || ($command->expectedLockVersion !== null && $command->expectedLockVersion < 0)) {
            $this->invalid(
                'expected_activation_id',
                'Expected activation and lock version must form a valid concurrency token.',
            );
        }

        return DB::transaction(function () use (
            $actor,
            $command,
            $tenantId,
            $reason,
        ): string {
            $this->locks->tenant($tenantId);
            $candidateSet = $this->locks->releaseGraph(
                tenantId: $tenantId,
                decisionId: $command->releaseDecisionId,
                allowedLifecycleStatuses: ['eligible', 'active'],
                denialReason: 'evaluation_gate',
                expectedAgentVersionId: $command->agentVersionId,
            );
            $this->compatibility->assertForActivation(
                $candidateSet,
                $command->scopeType,
                $command->scopeKey,
            );

            $open = $this->locks->activationScope(
                $tenantId,
                $command->scopeType,
                $command->scopeKey,
            );
            if ($open->count() > 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }
            $previous = $open->first();
            $this->assertConcurrencyToken(
                $previous,
                $command->expectedActivationId,
                $command->expectedLockVersion,
            );
            $control = $this->controlActivation(
                tenantId: $tenantId,
                previous: $previous,
                candidateAgentVersionId: $command->agentVersionId,
                candidateDecisionId: $command->releaseDecisionId,
            );
            if ($command->rolloutPercentage < 100 && $control === null) {
                $this->invalid(
                    'rollout_percentage',
                    'A partial rollout requires an eligible previous activation.',
                );
            }
            $previousSet = null;
            if ($command->rolloutPercentage < 100 && $control !== null) {
                if (! is_string($control->release_decision_id)
                    || ! Str::isUuid($control->release_decision_id)) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
                $previousSet = $this->locks->releaseGraph(
                    tenantId: $tenantId,
                    decisionId: $control->release_decision_id,
                    allowedLifecycleStatuses: ['eligible', 'active'],
                    denialReason: 'evaluation_gate',
                    expectedAgentVersionId: (string) $control->agent_version_id,
                );
                if (! hash_equals('active', (string) $previousSet->agent->lifecycle_status)) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
                $this->compatibility->assertForActivation(
                    $previousSet,
                    $command->scopeType,
                    $command->scopeKey,
                );
            }
            $this->eligibility->assertDecisionStillEligible(
                $tenantId,
                $candidateSet,
                'evaluation_gate',
            );
            if ($previousSet !== null) {
                $this->eligibility->assertDecisionStillEligible(
                    $tenantId,
                    $previousSet,
                    'evaluation_gate',
                );
            }

            if ($previous !== null) {
                $this->closeActivation(
                    (string) $previous->id,
                    (int) $previous->lock_version,
                );
            }

            $activationId = (string) Str::uuid();
            DB::table('ai_agent_activations')->insert([
                'id' => $activationId,
                'tenant_id' => $tenantId,
                'scope_type' => $command->scopeType,
                'scope_key' => $command->scopeKey,
                'agent_version_id' => $command->agentVersionId,
                'rollout_percentage' => $command->rolloutPercentage,
                'cohort_salt' => hash(
                    'sha256',
                    implode('|', [$tenantId, $command->scopeType, $command->scopeKey]),
                ),
                'release_decision_id' => $command->releaseDecisionId,
                'previous_activation_id' => $control?->id,
                'effective_from' => now(),
                'effective_until' => null,
                'activated_by' => (string) $actor->getKey(),
                'reason' => $reason,
                'lock_version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ai_agent_versions')
                ->where('tenant_id', $tenantId)
                ->where('id', $command->agentVersionId)
                ->whereIn('lifecycle_status', ['eligible', 'active'])
                ->where('kill_switch_enabled', false)
                ->update([
                    'lifecycle_status' => 'active',
                    'updated_at' => now(),
                ]);
            if ($previous !== null
                && $command->rolloutPercentage === 100
                && ! hash_equals(
                    (string) $control?->agent_version_id,
                    $command->agentVersionId,
                )) {
                $this->demoteIfNoLongerActive(
                    $tenantId,
                    (string) $control?->agent_version_id,
                );
            }

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $activationId,
                eventName: 'ai_registry.agent_activated',
                reason: $reason,
                attributes: [
                    'agent_version_id' => $command->agentVersionId,
                    'release_decision_id' => $command->releaseDecisionId,
                    'scope_type' => $command->scopeType,
                    'scope_key' => $command->scopeKey,
                    'rollout_percentage' => $command->rolloutPercentage,
                    'previous_activation_id' => $control?->id,
                ],
            );

            return $activationId;
        }, 3);
    }

    public function rollback(RollbackAgentActivationCommand $command): string
    {
        $this->assertCommandIds(
            $command->actorId,
            $command->tenantId,
            $command->activationId,
        );
        if ($command->expectedLockVersion < 0) {
            $this->invalid('expected_lock_version', 'Expected lock version must be non-negative.');
        }
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);

        return DB::transaction(function () use (
            $actor,
            $command,
            $tenantId,
            $reason,
        ): string {
            $this->locks->tenant($tenantId);
            $snapshot = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $command->activationId)
                ->whereNull('effective_until')
                ->first();
            if ($snapshot === null
                || (int) $snapshot->lock_version !== $command->expectedLockVersion
                || ! is_string($snapshot->previous_activation_id)
                || ! Str::isUuid($snapshot->previous_activation_id)) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            $previous = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $snapshot->previous_activation_id)
                ->first();
            if ($previous === null
                || ! is_string($previous->release_decision_id)
                || ! Str::isUuid($previous->release_decision_id)) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $targetSet = $this->locks->releaseGraph(
                tenantId: $tenantId,
                decisionId: (string) $previous->release_decision_id,
                allowedLifecycleStatuses: ['eligible', 'active'],
                denialReason: 'evaluation_gate',
                expectedAgentVersionId: (string) $previous->agent_version_id,
            );
            $current = $this->locks->activation($tenantId, $command->activationId);
            if ($current === null
                || $current->effective_until !== null
                || (int) $current->lock_version !== $command->expectedLockVersion
                || ! hash_equals(
                    (string) $snapshot->previous_activation_id,
                    (string) $current->previous_activation_id,
                )) {
                throw new AiExecutionDeniedException('registry_conflict');
            }
            $this->compatibility->assertForActivation(
                $targetSet,
                (string) $current->scope_type,
                (string) $current->scope_key,
            );
            $this->closeActivation((string) $current->id, (int) $current->lock_version);

            $rollbackId = (string) Str::uuid();
            DB::table('ai_agent_activations')->insert([
                'id' => $rollbackId,
                'tenant_id' => $tenantId,
                'scope_type' => $current->scope_type,
                'scope_key' => $current->scope_key,
                'agent_version_id' => $previous->agent_version_id,
                'rollout_percentage' => 100,
                'cohort_salt' => hash(
                    'sha256',
                    implode('|', [$tenantId, $current->scope_type, $current->scope_key]),
                ),
                'release_decision_id' => $previous->release_decision_id,
                'previous_activation_id' => $current->id,
                'effective_from' => now(),
                'effective_until' => null,
                'activated_by' => (string) $actor->getKey(),
                'reason' => $reason,
                'lock_version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ai_agent_versions')
                ->where('tenant_id', $tenantId)
                ->where('id', $previous->agent_version_id)
                ->whereIn('lifecycle_status', ['eligible', 'active'])
                ->where('kill_switch_enabled', false)
                ->update([
                    'lifecycle_status' => 'active',
                    'updated_at' => now(),
                ]);
            if (! hash_equals(
                (string) $current->agent_version_id,
                (string) $previous->agent_version_id,
            )) {
                $this->demoteIfNoLongerActive(
                    $tenantId,
                    (string) $current->agent_version_id,
                );
            }

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $rollbackId,
                eventName: 'ai_registry.activation_rolled_back',
                reason: $reason,
                attributes: [
                    'agent_version_id' => (string) $previous->agent_version_id,
                    'release_decision_id' => (string) $previous->release_decision_id,
                    'scope_type' => (string) $current->scope_type,
                    'scope_key' => (string) $current->scope_key,
                    'previous_activation_id' => (string) $current->id,
                ],
            );

            return $rollbackId;
        }, 3);
    }

    public function killSwitch(KillSwitchAiVersionCommand $command): void
    {
        $this->assertCommandIds($command->actorId, $command->tenantId, $command->versionId);
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);
        $type = $this->versionType($command->versionType);

        DB::transaction(function () use (
            $actor,
            $command,
            $tenantId,
            $reason,
            $type,
        ): void {
            $this->locks->tenant($tenantId);
            $version = $this->version(
                $type['table'],
                $type['tenant_scoped'],
                $tenantId,
                $command->versionId,
                true,
            );
            if ((bool) $version->kill_switch_enabled
                && hash_equals('blocked', (string) $version->lifecycle_status)) {
                return;
            }

            $updated = DB::table($type['table'])
                ->where('id', $command->versionId)
                ->where('lifecycle_status', $version->lifecycle_status)
                ->update([
                    'lifecycle_status' => 'blocked',
                    'kill_switch_enabled' => true,
                    'blocked_at' => now(),
                    'blocked_by' => (string) $actor->getKey(),
                    'blocked_reason' => $reason,
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            if ($command->versionType === 'agent') {
                $this->locks->agentActivations($tenantId, $command->versionId);
                DB::table('ai_agent_activations')
                    ->where('tenant_id', $tenantId)
                    ->where('agent_version_id', $command->versionId)
                    ->whereNull('effective_until')
                    ->update([
                        'effective_until' => now(),
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $command->versionId,
                eventName: 'ai_registry.version_blocked',
                reason: $reason,
                attributes: [
                    'version_type' => $command->versionType,
                    'previous_status' => (string) $version->lifecycle_status,
                ],
            );
        }, 3);
    }

    public function revokeReleaseDecision(RevokeAiReleaseDecisionCommand $command): void
    {
        $this->assertCommandIds(
            $command->actorId,
            $command->tenantId,
            $command->decisionId,
        );
        $actor = $this->platformActor($command->actorId);
        $tenantId = $this->tenantId($command->tenantId);
        $reason = $this->reason($command->reason);

        DB::transaction(function () use ($actor, $command, $tenantId, $reason): void {
            $this->locks->tenant($tenantId);
            $existing = DB::table('ai_release_decisions')
                ->where('tenant_id', $tenantId)
                ->where('id', $command->decisionId)
                ->first();
            if ($existing === null) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            if ($existing->revoked_at !== null) {
                if (hash_equals((string) $actor->getKey(), (string) $existing->revoked_by)
                    && hash_equals($reason, (string) $existing->revocation_reason)) {
                    return;
                }

                throw new AiExecutionDeniedException('registry_conflict');
            }

            $set = $this->locks->releaseGraph(
                tenantId: $tenantId,
                decisionId: $command->decisionId,
                allowedLifecycleStatuses: ['candidate', 'eligible', 'active'],
                denialReason: 'evaluation_gate',
            );
            $activations = $this->locks->decisionDependentActivations(
                $tenantId,
                $command->decisionId,
            );
            $revokedAt = now();
            $updated = DB::table('ai_release_decisions')
                ->where('tenant_id', $tenantId)
                ->where('id', $command->decisionId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revoked_by' => (string) $actor->getKey(),
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);
            if ($updated !== 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }
            if ($activations->isNotEmpty()) {
                DB::table('ai_agent_activations')
                    ->whereIn('id', $activations->pluck('id')->all())
                    ->whereNull('effective_until')
                    ->update([
                        'effective_until' => $revokedAt,
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => $revokedAt,
                    ]);
            }

            $this->record(
                actor: $actor,
                tenantId: $tenantId,
                aggregateId: $command->decisionId,
                eventName: 'ai_registry.release_decision_revoked',
                reason: $reason,
                attributes: [
                    'agent_version_id' => (string) $set->agent->id,
                    'closed_activation_count' => $activations->count(),
                ],
            );
        }, 3);
    }

    /**
     * @return array{0:string,1:string,2:object}
     */
    private function draftOwner(
        string $versionType,
        string $definitionId,
        string $tenantId,
    ): array {
        [$table, $ownerColumn, $ownerTable] = match ($versionType) {
            'provider' => ['ai_provider_versions', 'provider_id', 'ai_providers'],
            'model' => ['ai_model_versions', 'model_id', 'ai_models'],
            'agent' => ['ai_agent_versions', 'agent_definition_id', 'ai_agent_definitions'],
            'prompt' => ['ai_prompt_versions', 'prompt_definition_id', 'ai_prompt_definitions'],
            default => throw new AiExecutionDeniedException('registry_selection'),
        };
        $query = DB::table($ownerTable)
            ->where('id', $definitionId)
            ->where('status', 'active');
        if (in_array($versionType, ['agent', 'prompt'], true)) {
            $query->where('tenant_id', $tenantId);
        }
        $owner = $query->lockForUpdate()->first();
        if ($owner === null) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        return [$table, $ownerColumn, $owner];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function checksumPayload(string $versionType, array $payload): array
    {
        $safePayload = $payload;
        unset($safePayload['secret_reference']);
        $this->secretPolicy->assertSafePayload($safePayload);

        if ($versionType === 'provider') {
            $safePayload['secret_reference'] = $this->secretPolicy->assertExternalReference(
                $payload['secret_reference'] ?? null,
            );
        }
        if ($versionType !== 'prompt') {
            return $safePayload;
        }

        $template = $payload['template'] ?? null;
        $inputSchema = $payload['input_schema'] ?? [];
        if (! is_string($template)
            || trim($template) === ''
            || mb_strlen($template) > 100_000
            || ! is_array($inputSchema)) {
            $this->invalid('payload.template', 'A valid prompt payload is required.');
        }

        return [
            'template_hash' => hash('sha256', $template),
            'input_schema' => $inputSchema,
            'encryption_key_version' => $this->secretPolicy->encryptionKeyVersion(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function draftPayload(
        string $versionType,
        array $payload,
        string $tenantId,
        string $versionId,
        object $owner,
    ): array {
        return match ($versionType) {
            'provider' => $this->providerDraftPayload($payload),
            'model' => $this->modelDraftPayload($payload, $owner),
            'agent' => $this->agentDraftPayload($payload, $tenantId),
            'prompt' => $this->promptDraftPayload($payload, $tenantId, $versionId),
            default => throw new AiExecutionDeniedException('registry_selection'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function providerDraftPayload(array $payload): array
    {
        $configuration = $this->objectPayload($payload, 'configuration');
        $this->secretPolicy->assertSafePayload($configuration);
        $secretReference = $this->secretPolicy->assertExternalReference(
            $payload['secret_reference'] ?? null,
        );

        return [
            'secret_reference' => $secretReference,
            'status' => 'active',
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function modelDraftPayload(array $payload, object $owner): array
    {
        $providerVersionId = $payload['provider_version_id'] ?? null;
        if (! is_string($providerVersionId) || ! Str::isUuid($providerVersionId)) {
            $this->invalid('payload.provider_version_id', 'A valid provider version is required.');
        }
        $providerVersion = DB::table('ai_provider_versions')
            ->where('id', $providerVersionId)
            ->whereIn('lifecycle_status', ['draft', 'candidate', 'eligible', 'active'])
            ->where('kill_switch_enabled', false)
            ->first();
        if ($providerVersion === null
            || ! hash_equals((string) $owner->provider_id, (string) $providerVersion->provider_id)) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $configuration = $this->objectPayload($payload, 'configuration');
        $this->secretPolicy->assertSafePayload($configuration);
        $contextWindow = $this->nullableNonNegativeInteger($payload, 'context_window');
        $inputCost = $this->nullableNonNegativeInteger($payload, 'input_cost_minor');
        $outputCost = $this->nullableNonNegativeInteger($payload, 'output_cost_minor');
        $currency = $payload['currency'] ?? null;
        if ($currency !== null
            && (! is_string($currency) || preg_match('/^[A-Z]{3}$/', $currency) !== 1)) {
            $this->invalid('payload.currency', 'Currency must be an uppercase ISO-style code.');
        }

        return [
            'provider_version_id' => $providerVersionId,
            'context_window' => $contextWindow,
            'input_cost_minor' => $inputCost,
            'output_cost_minor' => $outputCost,
            'currency' => $currency,
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function agentDraftPayload(array $payload, string $tenantId): array
    {
        $modelVersionId = $payload['model_version_id'] ?? null;
        if (! is_string($modelVersionId)
                || ! Str::isUuid($modelVersionId)
                || ! DB::table('ai_model_versions')
                    ->where('id', $modelVersionId)
                    ->whereIn('lifecycle_status', ['draft', 'candidate', 'eligible', 'active'])
                    ->where('kill_switch_enabled', false)
                    ->exists()) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $configuration = $this->objectPayload($payload, 'configuration');
        $this->secretPolicy->assertSafePayload($configuration);
        $modelVersionIds = $configuration['model_version_ids'] ?? null;
        $promptVersionId = $configuration['prompt_version_id'] ?? null;
        if (! is_array($modelVersionIds)
            || ! in_array($modelVersionId, $modelVersionIds, true)
            || ! is_string($promptVersionId)
            || ! Str::isUuid($promptVersionId)
            || ! DB::table('ai_prompt_versions')
                ->where('tenant_id', $tenantId)
                ->where('id', $promptVersionId)
                ->whereIn('lifecycle_status', ['draft', 'candidate', 'eligible', 'active'])
                ->where('kill_switch_enabled', false)
                ->exists()) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        return [
            'model_version_id' => $modelVersionId,
            'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function promptDraftPayload(
        array $payload,
        string $tenantId,
        string $versionId,
    ): array {
        $template = $payload['template'] ?? null;
        if (! is_string($template)
            || trim($template) === ''
            || mb_strlen($template) > 100_000) {
            $this->invalid('payload.template', 'A non-empty bounded prompt template is required.');
        }
        $inputSchema = $payload['input_schema'] ?? [];
        if (! is_array($inputSchema)) {
            $this->invalid('payload.input_schema', 'Prompt input schema must be a JSON structure.');
        }
        $this->secretPolicy->assertSafePayload($inputSchema);
        $encrypted = $this->cipher->encrypt(
            $tenantId,
            $versionId,
            'prompt_template',
            $template,
        );

        return [
            'template_ciphertext' => $encrypted->ciphertext,
            'template_hash' => $encrypted->contentHash,
            'encryption_key_version' => $encrypted->keyVersion,
            'input_schema' => json_encode($inputSchema, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function objectPayload(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->invalid("payload.{$key}", 'Registry configuration must be a JSON object.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function nullableNonNegativeInteger(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_int($value) || $value < 0) {
            $this->invalid("payload.{$key}", 'Registry numeric values must be non-negative integers.');
        }

        return $value;
    }

    /**
     * @return array{table:string,tenant_scoped:bool}
     */
    private function versionType(string $versionType): array
    {
        $type = self::VERSION_TYPES[$versionType] ?? null;
        if ($type === null) {
            $this->invalid('version_type', 'Unsupported registry version type.');
        }

        return $type;
    }

    private function platformActor(string $actorId): User
    {
        $actor = User::query()->withoutGlobalScopes()->find($actorId);
        if (! $actor instanceof User || ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a platform super administrator may mutate the AI registry.');
        }

        return $actor;
    }

    private function tenantId(?string $tenantId): string
    {
        $resolved = $tenantId ?? config('ai.public.billing_tenant_id');
        if (! is_string($resolved)
            || ! Str::isUuid($resolved)
            || ! DB::table('tenants')->where('id', $resolved)->exists()) {
            $this->invalid('tenant_id', 'An explicit valid registry owner tenant is required.');
        }

        return $resolved;
    }

    private function reason(string $reason): string
    {
        $normalized = trim($reason);
        if (mb_strlen($normalized) < 10 || mb_strlen($normalized) > 500) {
            $this->invalid('reason', 'An auditable reason between 10 and 500 characters is required.');
        }
        $this->secretPolicy->assertSafeReason($normalized);

        return $normalized;
    }

    private function assertCommandIds(string $actorId, ?string $tenantId, string ...$ids): void
    {
        if (! Str::isUuid($actorId)
            || ($tenantId !== null && ! Str::isUuid($tenantId))
            || collect($ids)->contains(static fn (string $id): bool => ! Str::isUuid($id))) {
            $this->invalid('id', 'Registry mutations require valid UUID identifiers.');
        }
    }

    private function assertScope(string $tenantId, string $scopeType, string $scopeKey): void
    {
        $valid = match ($scopeType) {
            'platform' => hash_equals('*', $scopeKey),
            'platform_channel' => preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $scopeKey) === 1,
            'market' => preg_match('/^[A-Z]{2}$/', $scopeKey) === 1,
            'market_channel' => preg_match('/^[A-Z]{2}\\|[a-z][a-z0-9_-]{0,31}$/', $scopeKey) === 1,
            'tenant' => hash_equals($tenantId, $scopeKey),
            'tenant_channel' => preg_match(
                '/^'.preg_quote($tenantId, '/').'\\|[a-z][a-z0-9_-]{0,31}$/',
                $scopeKey,
            ) === 1,
            default => false,
        };
        if (! $valid) {
            $this->invalid('scope_key', 'Registry activation scope is invalid or ambiguous.');
        }
    }

    private function assertConcurrencyToken(
        ?object $current,
        ?string $expectedActivationId,
        ?int $expectedLockVersion,
    ): void {
        if ($current === null) {
            if ($expectedActivationId !== null || $expectedLockVersion !== null) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            return;
        }
        if ($expectedActivationId === null
            || $expectedLockVersion === null
            || ! hash_equals((string) $current->id, $expectedActivationId)
            || (int) $current->lock_version !== $expectedLockVersion) {
            throw new AiExecutionDeniedException('registry_conflict');
        }
    }

    private function closeActivation(string $activationId, int $lockVersion): void
    {
        $updated = DB::table('ai_agent_activations')
            ->where('id', $activationId)
            ->whereNull('effective_until')
            ->where('lock_version', $lockVersion)
            ->update([
                'effective_until' => now(),
                'lock_version' => $lockVersion + 1,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new AiExecutionDeniedException('registry_conflict');
        }
    }

    private function controlActivation(
        string $tenantId,
        ?object $previous,
        string $candidateAgentVersionId,
        string $candidateDecisionId,
    ): ?object {
        if ($previous === null
            || ! hash_equals((string) $previous->agent_version_id, $candidateAgentVersionId)
            || ! hash_equals((string) $previous->release_decision_id, $candidateDecisionId)
            || ! is_string($previous->previous_activation_id)
            || ! Str::isUuid($previous->previous_activation_id)) {
            return $previous;
        }

        $control = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('id', $previous->previous_activation_id)
            ->lockForUpdate()
            ->first();
        if ($control === null) {
            throw new AiExecutionDeniedException('registry_conflict');
        }

        return $control;
    }

    private function demoteIfNoLongerActive(string $tenantId, string $agentVersionId): void
    {
        if (DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $agentVersionId)
            ->whereNull('effective_until')
            ->exists()
            || DB::table('ai_agent_activations as current')
                ->join(
                    'ai_agent_activations as fallback',
                    'fallback.id',
                    '=',
                    'current.previous_activation_id',
                )
                ->where('current.tenant_id', $tenantId)
                ->whereNull('current.effective_until')
                ->where('current.rollout_percentage', '<', 100)
                ->where('fallback.agent_version_id', $agentVersionId)
                ->exists()) {
            return;
        }

        DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $agentVersionId)
            ->where('lifecycle_status', 'active')
            ->where('kill_switch_enabled', false)
            ->update([
                'lifecycle_status' => 'eligible',
                'updated_at' => now(),
            ]);
    }

    private function assertCandidateDependencies(
        string $versionType,
        object $version,
        string $tenantId,
    ): void {
        if ($versionType === 'model') {
            $valid = DB::table('ai_provider_versions')
                ->where('id', $version->provider_version_id)
                ->whereIn('lifecycle_status', ['candidate', 'eligible', 'active'])
                ->where('kill_switch_enabled', false)
                ->exists();
        } elseif ($versionType === 'agent') {
            $configuration = $this->jsonObject($version->configuration);
            $promptVersionId = $configuration['prompt_version_id'] ?? null;
            $modelVersionIds = $configuration['model_version_ids'] ?? null;
            $valid = is_string($promptVersionId)
                && is_array($modelVersionIds)
                && in_array((string) $version->model_version_id, $modelVersionIds, true)
                && DB::table('ai_prompt_versions')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $promptVersionId)
                    ->whereIn('lifecycle_status', ['candidate', 'eligible', 'active'])
                    ->where('kill_switch_enabled', false)
                    ->exists()
                && DB::table('ai_model_versions')
                    ->where('id', $version->model_version_id)
                    ->whereIn('lifecycle_status', ['candidate', 'eligible', 'active'])
                    ->where('kill_switch_enabled', false)
                    ->exists();
        } else {
            $valid = true;
        }
        if (! $valid) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }
    }

    private function promoteCandidateSet(
        string $tenantId,
        AiReleaseCandidateSet $set,
    ): void {
        foreach ([
            ['ai_provider_versions', $set->provider->id, false],
            ['ai_model_versions', $set->model->id, false],
            ['ai_prompt_versions', $set->prompt->id, true],
            ['ai_agent_versions', $set->agent->id, true],
        ] as [$table, $versionId, $tenantScoped]) {
            $query = DB::table($table)
                ->where('id', $versionId)
                ->where('lifecycle_status', 'candidate')
                ->where('kill_switch_enabled', false);
            if ($tenantScoped) {
                $query->where('tenant_id', $tenantId);
            }
            $query->update([
                'lifecycle_status' => 'eligible',
                'updated_at' => now(),
            ]);
        }
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $items = array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item) && trim($item) !== '',
        ));

        return count($items) === count($value) && count(array_unique($items)) === count($items)
            ? $items
            : [];
    }

    private function version(
        string $table,
        bool $tenantScoped,
        string $tenantId,
        string $versionId,
        bool $lock,
    ): object {
        $query = DB::table($table)->where('id', $versionId);
        if ($tenantScoped) {
            $query->where('tenant_id', $tenantId);
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        $version = $query->first();
        if ($version === null) {
            throw new AiExecutionDeniedException('registry_selection');
        }

        return $version;
    }

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, scalar|null>  $attributes
     */
    private function record(
        User $actor,
        string $tenantId,
        string $aggregateId,
        string $eventName,
        string $reason,
        array $attributes,
    ): void {
        activity('ai-registry')
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => $tenantId,
                'aggregate_id' => $aggregateId,
                'reason' => $reason,
                ...$attributes,
            ])
            ->event($eventName)
            ->log($eventName);

        $this->outbox->append(new AiRegistryChanged(
            aggregateId: $aggregateId,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            eventName: $eventName,
            reason: $reason,
            attributes: $attributes,
            occurredAt: now()->toImmutable(),
        ));
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
