<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\CreateAiDraftCommand;
use App\Application\Ai\Registry\Commands\KillSwitchAiVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
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
        'eligible' => ['active'],
        'active' => ['retired'],
        'retired' => [],
        'blocked' => [],
    ];

    public function __construct(
        private OutboxRepositoryInterface $outbox,
        private AiRegistryChecksum $checksum,
        private LaravelAiContentCipher $cipher,
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
            [$table, $ownerColumn, $owner] = $this->draftOwner(
                $command->versionType,
                $command->definitionId,
                $tenantId,
            );
            $checksum = $this->checksum->for([
                'version_type' => $command->versionType,
                'definition_id' => $command->definitionId,
                'payload' => $command->payload,
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

            if ($command->targetStatus === 'eligible') {
                if (! is_string($command->releaseDecisionId)
                    || ! Str::isUuid($command->releaseDecisionId)) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
                $this->assertExactDecision(
                    $tenantId,
                    $command->releaseDecisionId,
                    $command->versionType,
                    $command->versionId,
                );
            } elseif ($command->releaseDecisionId !== null) {
                $this->invalid(
                    'release_decision_id',
                    'A release decision is accepted only for eligibility promotion.',
                );
            }

            if ($command->targetStatus === 'retired'
                && $command->versionType === 'agent'
                && DB::table('ai_agent_activations')
                    ->where('tenant_id', $tenantId)
                    ->where('agent_version_id', $command->versionId)
                    ->whereNull('effective_until')
                    ->exists()) {
                $this->invalid('target_status', 'An actively assigned agent version cannot be retired.');
            }

            $attributes = [
                'lifecycle_status' => $command->targetStatus,
                'updated_at' => now(),
            ];
            if ($command->targetStatus === 'candidate' && $version->released_at === null) {
                $attributes['released_at'] = now();
            }

            $updated = DB::table($type['table'])
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
            $this->lockTenant($tenantId);
            $candidate = $this->version(
                'ai_agent_versions',
                true,
                $tenantId,
                $command->agentVersionId,
                true,
            );
            if (! in_array((string) $candidate->lifecycle_status, ['eligible', 'active'], true)
                || (bool) $candidate->kill_switch_enabled) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $this->assertExactDecision(
                $tenantId,
                $command->releaseDecisionId,
                'agent',
                $command->agentVersionId,
            );

            $open = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('scope_type', $command->scopeType)
                ->where('scope_key', $command->scopeKey)
                ->whereNull('effective_until')
                ->lockForUpdate()
                ->get();
            if ($open->count() > 1) {
                throw new AiExecutionDeniedException('registry_conflict');
            }
            $previous = $open->first();
            $this->assertConcurrencyToken(
                $previous,
                $command->expectedActivationId,
                $command->expectedLockVersion,
            );
            if ($command->rolloutPercentage < 100 && $previous === null) {
                $this->invalid(
                    'rollout_percentage',
                    'A partial rollout requires an eligible previous activation.',
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
                'previous_activation_id' => $previous?->id,
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
                && ! hash_equals(
                    (string) $previous->agent_version_id,
                    $command->agentVersionId,
                )) {
                $this->demoteIfNoLongerActive(
                    $tenantId,
                    (string) $previous->agent_version_id,
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
                    'previous_activation_id' => $previous?->id,
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
            $this->lockTenant($tenantId);
            $current = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $command->activationId)
                ->whereNull('effective_until')
                ->lockForUpdate()
                ->first();
            if ($current === null
                || (int) $current->lock_version !== $command->expectedLockVersion
                || ! is_string($current->previous_activation_id)
                || ! Str::isUuid($current->previous_activation_id)) {
                throw new AiExecutionDeniedException('registry_conflict');
            }

            $previous = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $current->previous_activation_id)
                ->first();
            if ($previous === null
                || ! is_string($previous->release_decision_id)
                || ! Str::isUuid($previous->release_decision_id)) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $target = $this->version(
                'ai_agent_versions',
                true,
                $tenantId,
                (string) $previous->agent_version_id,
                true,
            );
            if (! in_array((string) $target->lifecycle_status, ['eligible', 'active'], true)
                || (bool) $target->kill_switch_enabled) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $this->assertExactDecision(
                $tenantId,
                (string) $previous->release_decision_id,
                'agent',
                (string) $previous->agent_version_id,
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
            'agent' => $this->agentDraftPayload($payload),
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
        $this->assertNoInlineSecrets($configuration);
        $secretReference = $payload['secret_reference'] ?? null;
        if ($secretReference !== null
            && (! is_string($secretReference)
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\\/-]{2,511}$/', $secretReference) !== 1
                || preg_match('/^(sk-|eyJ|-----BEGIN)/i', $secretReference) === 1)) {
            $this->invalid('payload.secret_reference', 'Only an external secret reference is accepted.');
        }

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
            ->whereIn('lifecycle_status', ['eligible', 'active'])
            ->where('kill_switch_enabled', false)
            ->first();
        if ($providerVersion === null
            || ! hash_equals((string) $owner->provider_id, (string) $providerVersion->provider_id)) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $configuration = $this->objectPayload($payload, 'configuration');
        $this->assertNoInlineSecrets($configuration);
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
    private function agentDraftPayload(array $payload): array
    {
        $modelVersionId = $payload['model_version_id'] ?? null;
        if (! is_string($modelVersionId)
            || ! Str::isUuid($modelVersionId)
            || ! DB::table('ai_model_versions')
                ->where('id', $modelVersionId)
                ->whereIn('lifecycle_status', ['eligible', 'active'])
                ->where('kill_switch_enabled', false)
                ->exists()) {
            throw new AiExecutionDeniedException('registry_selection');
        }
        $configuration = $this->objectPayload($payload, 'configuration');
        $this->assertNoInlineSecrets($configuration);

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
        $this->assertNoInlineSecrets($inputSchema);
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

    /** @param array<string|int, mixed> $payload */
    private function assertNoInlineSecrets(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key)
                && preg_match('/secret|api[_-]?key|password|credential|bearer/i', $key) === 1) {
                $this->invalid('payload', 'Inline credentials are forbidden in registry drafts.');
            }
            if (is_array($value)) {
                $this->assertNoInlineSecrets($value);
            } elseif (is_string($value)
                && preg_match('/(^|\\s)(sk-[A-Za-z0-9_-]{8,}|-----BEGIN [A-Z ]*PRIVATE KEY)/', $value) === 1) {
                $this->invalid('payload', 'Inline credentials are forbidden in registry drafts.');
            }
        }
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

    private function lockTenant(string $tenantId): void
    {
        if (DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first() === null) {
            $this->invalid('tenant_id', 'Registry owner tenant does not exist.');
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

    private function demoteIfNoLongerActive(string $tenantId, string $agentVersionId): void
    {
        if (DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $agentVersionId)
            ->whereNull('effective_until')
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

    private function assertExactDecision(
        string $tenantId,
        string $decisionId,
        string $targetType,
        string $targetVersionId,
    ): void {
        $latest = DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('id', $decisionId)
            ->first();
        if ($latest === null
            || ! is_string($latest->agent_version_id)
            || ! Str::isUuid($latest->agent_version_id)) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $ordered = DB::table('ai_release_decisions')
            ->where('tenant_id', $tenantId)
            ->where('agent_version_id', $latest->agent_version_id)
            ->orderByDesc('decided_at')
            ->limit(2)
            ->get();
        $newest = $ordered->first();
        if ($newest === null
            || ! hash_equals($decisionId, (string) $newest->id)
            || ($ordered->count() > 1
                && hash_equals(
                    (string) $newest->decided_at,
                    (string) $ordered->get(1)->decided_at,
                ))) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $decision = DB::table('ai_release_decisions as decisions')
            ->join('ai_release_policies as policies', function ($join): void {
                $join->on('policies.tenant_id', '=', 'decisions.tenant_id')
                    ->on('policies.id', '=', 'decisions.release_policy_id');
            })
            ->join('ai_evaluation_runs as runs', function ($join): void {
                $join->on('runs.tenant_id', '=', 'decisions.tenant_id')
                    ->on('runs.id', '=', 'decisions.evaluation_run_id');
            })
            ->join('ai_evaluation_versions as evaluation_versions', function ($join): void {
                $join->on('evaluation_versions.tenant_id', '=', 'runs.tenant_id')
                    ->on('evaluation_versions.id', '=', 'runs.evaluation_version_id');
            })
            ->join('ai_evaluation_suites as suites', function ($join): void {
                $join->on('suites.tenant_id', '=', 'evaluation_versions.tenant_id')
                    ->on('suites.id', '=', 'evaluation_versions.evaluation_suite_id');
            })
            ->where('decisions.tenant_id', $tenantId)
            ->where('decisions.id', $decisionId)
            ->where('decisions.decision', 'passed')
            ->where('policies.status', 'active')
            ->where('runs.status', 'completed')
            ->where('suites.status', 'active')
            ->whereNotNull('evaluation_versions.released_at')
            ->whereNotNull('decisions.candidate_checksum')
            ->whereNotNull('decisions.valid_from')
            ->where('decisions.valid_from', '<=', now())
            ->whereNull('decisions.revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('decisions.valid_until')
                    ->orWhere('decisions.valid_until', '>', now());
            })
            ->whereNotNull('runs.started_at')
            ->whereNotNull('runs.completed_at')
            ->whereColumn('runs.started_at', '<=', 'runs.completed_at')
            ->whereColumn('runs.completed_at', '<=', 'decisions.decided_at')
            ->where('runs.completed_at', '<=', now())
            ->select([
                'decisions.agent_version_id',
                'decisions.candidate_checksum',
                'decisions.decided_at',
                'runs.id as evaluation_run_id',
                'runs.evaluation_version_id',
                'runs.agent_version_id as run_agent_version_id',
                'runs.prompt_version_id',
                'runs.provider_version_id',
                'runs.model_version_id',
            ])
            ->first();
        if ($decision === null
            || ! hash_equals(
                (string) $decision->agent_version_id,
                (string) $decision->run_agent_version_id,
            )) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $agent = $this->version(
            'ai_agent_versions',
            true,
            $tenantId,
            (string) $decision->agent_version_id,
            false,
        );
        if (! hash_equals((string) $agent->checksum, (string) $decision->candidate_checksum)
            || (bool) $agent->kill_switch_enabled
            || ! in_array(
                (string) $agent->lifecycle_status,
                $targetType === 'agent' && hash_equals($targetVersionId, (string) $agent->id)
                    ? ['candidate', 'eligible', 'active']
                    : ['eligible', 'active'],
                true,
            )) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $configuration = $this->jsonObject($agent->configuration);
        $promptVersionId = $configuration['prompt_version_id'] ?? null;
        $modelVersionIds = $configuration['model_version_ids'] ?? null;
        if (! is_string($promptVersionId)
            || ! is_array($modelVersionIds)
            || ! in_array($decision->model_version_id, $modelVersionIds, true)
            || ! hash_equals($promptVersionId, (string) $decision->prompt_version_id)) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $prompt = $this->version(
            'ai_prompt_versions',
            true,
            $tenantId,
            (string) $decision->prompt_version_id,
            false,
        );
        $provider = $this->version(
            'ai_provider_versions',
            false,
            $tenantId,
            (string) $decision->provider_version_id,
            false,
        );
        $model = $this->version(
            'ai_model_versions',
            false,
            $tenantId,
            (string) $decision->model_version_id,
            false,
        );
        foreach ([
            'prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
        ] as $versionType => $version) {
            $allowed = $targetType === $versionType
                && hash_equals($targetVersionId, (string) $version->id)
                    ? ['candidate', 'eligible', 'active']
                    : ['eligible', 'active'];
            if ((bool) $version->kill_switch_enabled
                || ! in_array((string) $version->lifecycle_status, $allowed, true)) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
        }

        $providerId = DB::table('ai_provider_versions')
            ->where('id', $decision->provider_version_id)
            ->value('provider_id');
        $modelProviderId = DB::table('ai_model_versions as versions')
            ->join('ai_models as models', 'models.id', '=', 'versions.model_id')
            ->where('versions.id', $decision->model_version_id)
            ->where('versions.provider_version_id', $decision->provider_version_id)
            ->where('models.status', 'active')
            ->value('models.provider_id');
        if (! is_string($providerId)
            || ! is_string($modelProviderId)
            || ! hash_equals($providerId, $modelProviderId)
            || ! DB::table('ai_providers')
                ->where('id', $providerId)
                ->where('status', 'active')
                ->exists()
            || ! DB::table('ai_agent_definitions')
                ->where('tenant_id', $tenantId)
                ->where('id', $agent->agent_definition_id)
                ->where('status', 'active')
                ->exists()
            || ! DB::table('ai_prompt_definitions')
                ->where('tenant_id', $tenantId)
                ->where('id', $prompt->prompt_definition_id)
                ->where('status', 'active')
                ->exists()) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $caseCount = DB::table('ai_evaluation_cases')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_version_id', $decision->evaluation_version_id)
            ->count();
        $resultCount = DB::table('ai_evaluation_results')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_run_id', $decision->evaluation_run_id)
            ->count();
        $passedCount = DB::table('ai_evaluation_results')
            ->where('tenant_id', $tenantId)
            ->where('evaluation_run_id', $decision->evaluation_run_id)
            ->where('status', 'passed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $decision->decided_at)
            ->where('completed_at', '<=', now())
            ->count();
        if ($caseCount < 1 || $resultCount !== $caseCount || $passedCount !== $caseCount) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        $actualTargetId = match ($targetType) {
            'agent' => (string) $decision->agent_version_id,
            'prompt' => (string) $decision->prompt_version_id,
            'provider' => (string) $decision->provider_version_id,
            'model' => (string) $decision->model_version_id,
            default => '',
        };
        if (! hash_equals($targetVersionId, $actualTargetId)) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }
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
