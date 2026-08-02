<?php

declare(strict_types=1);

namespace App\Infrastructure\Automation;

use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseWorkflowRepository implements WorkflowRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function findToolPolicy(string $tenantId, string $tool, string $toolVersion): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_tool_policies')
                ->where('tenant_id', $tenantId)
                ->where('tool', $tool)
                ->where('tool_version', $toolVersion)
                ->first(),
            ['allowed_roles', 'allowed_plans', 'allowed_channels'],
        );
    }

    public function claimIdempotency(
        string $tenantId,
        string $scope,
        string $idempotencyKey,
        string $fingerprint,
        CarbonImmutable $occurredAt,
    ): bool {
        try {
            $this->connection->table('automation_integration_idempotency')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'scope' => $scope,
                'idempotency_key' => $idempotencyKey,
                'fingerprint' => $fingerprint,
                'consumed_at' => $occurredAt->toIso8601String(),
                'created_at' => $occurredAt->toIso8601String(),
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    public function hasIdempotencyClaim(string $tenantId, string $scope, string $idempotencyKey): bool
    {
        return $this->connection->table('automation_integration_idempotency')
            ->where('tenant_id', $tenantId)
            ->where('scope', $scope)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    public function claimAgentActionIdempotency(
        string $tenantId,
        string $actionKey,
        string $idempotencyKey,
        string $fingerprint,
        CarbonImmutable $occurredAt,
    ): bool {
        return $this->claimIdempotency(
            $tenantId,
            'agent_action:'.$actionKey,
            $idempotencyKey,
            $fingerprint,
            $occurredAt,
        );
    }

    /** @return array<string, mixed>|null */
    public function findAgentActionAuditByIdempotency(string $tenantId, string $actionKey, string $idempotencyKey): ?array
    {
        return $this->normalize(
            $this->connection->table('dovra_agent_action_audits')
                ->where('tenant_id', $tenantId)
                ->where('action_key', $actionKey)
                ->where('idempotency_key', $idempotencyKey)
                ->first(),
            ['metadata'],
        );
    }

    /** @return array<string, mixed>|null */
    public function findAgentActionAuditById(string $auditId, string $tenantId): ?array
    {
        return $this->normalize(
            $this->connection->table('dovra_agent_action_audits')
                ->where('id', $auditId)
                ->where('tenant_id', $tenantId)
                ->first(),
            ['metadata'],
        );
    }

    /** @param array<string, mixed> $attributes */
    public function createAgentActionAudit(array $attributes): void
    {
        $attributes['metadata'] = isset($attributes['metadata'])
            ? json_encode($attributes['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $this->connection->table('dovra_agent_action_audits')->insert($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function updateAgentActionAudit(string $auditId, array $attributes): void
    {
        if (isset($attributes['metadata']) && is_array($attributes['metadata'])) {
            $attributes['metadata'] = json_encode($attributes['metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $this->connection->table('dovra_agent_action_audits')->where('id', $auditId)->update($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function createAgentOutcome(array $attributes): void
    {
        $attributes['attribution_metadata'] = isset($attributes['attribution_metadata'])
            ? json_encode($attributes['attribution_metadata'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $this->connection->table('dovra_agent_outcomes')->insert($attributes);
    }

    public function lockConfirmationAttempt(string $confirmationId): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_confirmation_attempts')
                ->where('id', $confirmationId)
                ->lockForUpdate()
                ->first(),
        );
    }

    public function updateConfirmationAttempt(string $confirmationId, string $status, CarbonImmutable $occurredAt): void
    {
        $attributes = [
            'status' => $status,
            'updated_at' => $occurredAt->toIso8601String(),
        ];

        if ($status === 'consumed') {
            $attributes['consumed_at'] = $occurredAt->toIso8601String();
        }

        $this->connection->table('automation_confirmation_attempts')
            ->where('id', $confirmationId)
            ->update($attributes);
    }

    public function createDraftVersion(
        string $tenantId,
        string $workflowKey,
        array $definition,
        string $actorId,
    ): array {
        $occurredAt = CarbonImmutable::now();
        $definitionRecord = $this->connection->table('automation_workflow_definitions')
            ->where('tenant_id', $tenantId)
            ->where('workflow_key', $workflowKey)
            ->first();

        if ($definitionRecord === null) {
            $definitionId = (string) Str::uuid();
            $this->connection->table('automation_workflow_definitions')->insert([
                'id' => $definitionId,
                'tenant_id' => $tenantId,
                'workflow_key' => $workflowKey,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_at' => $occurredAt->toIso8601String(),
                'updated_at' => $occurredAt->toIso8601String(),
            ]);
        } else {
            $definitionId = (string) $definitionRecord->id;
        }

        $versionId = (string) Str::uuid();
        $versionName = 'draft-'.Str::lower(Str::random(8));

        $this->connection->table('automation_workflow_versions')->insert([
            'id' => $versionId,
            'definition_id' => $definitionId,
            'tenant_id' => $tenantId,
            'workflow_key' => $workflowKey,
            'version' => $versionName,
            'status' => 'draft',
            'definition' => json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $actorId,
            'created_at' => $occurredAt->toIso8601String(),
            'updated_at' => $occurredAt->toIso8601String(),
        ]);

        $this->connection->table('automation_workflow_definitions')
            ->where('id', $definitionId)
            ->update([
                'current_draft_version_id' => $versionId,
                'updated_by' => $actorId,
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

        return [
            'definition_id' => $definitionId,
            'version_id' => $versionId,
            'version' => $versionName,
        ];
    }

    public function publishDraftVersion(
        string $tenantId,
        string $workflowKey,
        string $draftVersionId,
        string $publishedVersion,
        string $actorId,
        CarbonImmutable $occurredAt,
    ): array {
        $version = $this->normalize(
            $this->connection->table('automation_workflow_versions')
                ->where('id', $draftVersionId)
                ->where('tenant_id', $tenantId)
                ->where('workflow_key', $workflowKey)
                ->first(),
            ['definition'],
        );

        if ($version === null || (string) $version['status'] !== 'draft') {
            throw new RuntimeException('Draft workflow version not found.');
        }

        $this->connection->table('automation_workflow_versions')
            ->where('id', $draftVersionId)
            ->update([
                'version' => $publishedVersion,
                'status' => 'published',
                'published_at' => $occurredAt->toIso8601String(),
                'published_by' => $actorId,
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

        $this->connection->table('automation_workflow_definitions')
            ->where('id', $version['definition_id'])
            ->update([
                'current_published_version_id' => $draftVersionId,
                'updated_by' => $actorId,
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

        return [
            'definition_id' => $version['definition_id'],
            'version_id' => $draftVersionId,
            'version' => $publishedVersion,
        ];
    }

    public function updateDraftVersion(
        string $tenantId,
        string $versionId,
        array $definition,
        CarbonImmutable $occurredAt,
    ): void {
        $version = $this->normalize(
            $this->connection->table('automation_workflow_versions')
                ->where('id', $versionId)
                ->where('tenant_id', $tenantId)
                ->first(),
            ['definition'],
        );

        if ($version === null) {
            throw new RuntimeException('Draft workflow version not found.');
        }

        if ((string) $version['status'] !== 'draft') {
            throw new RuntimeException('Published workflow versions are immutable.');
        }

        $this->connection->table('automation_workflow_versions')
            ->where('id', $versionId)
            ->update([
                'definition' => json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $occurredAt->toIso8601String(),
            ]);
    }

    public function findPublishedVersion(string $tenantId, string $workflowKey, string $version): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_versions')
                ->where('tenant_id', $tenantId)
                ->where('workflow_key', $workflowKey)
                ->where('version', $version)
                ->where('status', 'published')
                ->first(),
            ['definition'],
        );
    }

    public function loadWorkflowVersion(string $workflowVersionId): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_versions')
                ->where('id', $workflowVersionId)
                ->first(),
            ['definition'],
        );
    }

    public function loadWorkflowVersionForTenant(string $workflowVersionId, string $tenantId): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_versions')
                ->where('id', $workflowVersionId)
                ->where('tenant_id', $tenantId)
                ->first(),
            ['definition'],
        );
    }

    public function findWorkflowRunByIdempotency(
        string $tenantId,
        string $workflowKey,
        string $workflowVersion,
        string $idempotencyKey,
    ): ?array {
        return $this->normalize(
            $this->connection->table('automation_workflow_runs')
                ->where('tenant_id', $tenantId)
                ->where('workflow_key', $workflowKey)
                ->where('workflow_version', $workflowVersion)
                ->where('idempotency_key', $idempotencyKey)
                ->first(),
            ['payload', 'output'],
        );
    }

    public function findOrCreateWorkflowRun(
        string $tenantId,
        string $workflowKey,
        string $workflowVersionId,
        string $workflowVersion,
        string $actorId,
        string $plan,
        string $channel,
        string $trigger,
        string $idempotencyKey,
        array $payload,
        CarbonImmutable $occurredAt,
    ): array {
        return $this->connection->transaction(function () use (
            $tenantId,
            $workflowKey,
            $workflowVersionId,
            $workflowVersion,
            $actorId,
            $plan,
            $channel,
            $trigger,
            $idempotencyKey,
            $payload,
            $occurredAt,
        ): array {
            $inserted = $this->connection->table('automation_workflow_runs')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'workflow_key' => $workflowKey,
                'workflow_version_id' => $workflowVersionId,
                'workflow_version' => $workflowVersion,
                'actor_id' => $actorId,
                'plan' => $plan,
                'channel' => $channel,
                'trigger_source' => $trigger,
                'idempotency_key' => $idempotencyKey,
                'status' => 'running',
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'started_at' => $occurredAt->toIso8601String(),
                'created_at' => $occurredAt->toIso8601String(),
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

            $run = $this->findWorkflowRunByIdempotency($tenantId, $workflowKey, $workflowVersion, $idempotencyKey);
            if ($run === null) {
                throw new RuntimeException('Workflow run could not be durably created.');
            }

            return [
                'run' => $run,
                'replayed' => $inserted === 0,
            ];
        }, 3);
    }

    public function createWorkflowRun(
        string $tenantId,
        string $workflowKey,
        string $workflowVersionId,
        string $workflowVersion,
        string $actorId,
        string $plan,
        string $channel,
        string $trigger,
        string $idempotencyKey,
        array $payload,
        CarbonImmutable $occurredAt,
    ): array {
        /** @var array<string, mixed> $run */
        $run = $this->findOrCreateWorkflowRun(
            tenantId: $tenantId,
            workflowKey: $workflowKey,
            workflowVersionId: $workflowVersionId,
            workflowVersion: $workflowVersion,
            actorId: $actorId,
            plan: $plan,
            channel: $channel,
            trigger: $trigger,
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            occurredAt: $occurredAt,
        )['run'];

        return $run;
    }

    public function loadWorkflowRun(string $runId): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_runs')
                ->where('id', $runId)
                ->first(),
            ['payload', 'output'],
        );
    }

    public function loadWorkflowRunForTenant(string $runId, string $tenantId): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_runs')
                ->where('id', $runId)
                ->where('tenant_id', $tenantId)
                ->first(),
            ['payload', 'output'],
        );
    }

    public function updateWorkflowRun(string $runId, array $attributes): void
    {
        if (isset($attributes['output']) && is_array($attributes['output'])) {
            $attributes['output'] = json_encode($attributes['output'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $attributes['updated_at'] = CarbonImmutable::now()->toIso8601String();

        $this->connection->table('automation_workflow_runs')
            ->where('id', $runId)
            ->update($attributes);
    }

    public function loadWorkflowStep(string $runId, string $stepKey): ?array
    {
        return $this->normalize(
            $this->connection->table('automation_workflow_steps')
                ->where('run_id', $runId)
                ->where('step_key', $stepKey)
                ->first(),
            ['payload', 'output'],
        );
    }

    public function saveWorkflowStep(string $runId, string $tenantId, string $stepKey, array $attributes): array
    {
        $existing = $this->loadWorkflowStep($runId, $stepKey);
        $occurredAt = CarbonImmutable::now()->toIso8601String();

        if (isset($attributes['payload']) && is_array($attributes['payload'])) {
            $attributes['payload'] = json_encode($attributes['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (isset($attributes['output']) && is_array($attributes['output'])) {
            $attributes['output'] = json_encode($attributes['output'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($existing === null) {
            $this->connection->table('automation_workflow_steps')->insert([
                'id' => (string) Str::uuid(),
                'run_id' => $runId,
                'tenant_id' => $tenantId,
                'step_key' => $stepKey,
                'step_type' => $attributes['step_type'] ?? 'tool',
                'action' => $attributes['action'] ?? null,
                'status' => $attributes['status'] ?? 'pending',
                'attempts' => $attributes['attempts'] ?? 0,
                'payload' => $attributes['payload'] ?? null,
                'output' => $attributes['output'] ?? null,
                'compensation_action' => $attributes['compensation_action'] ?? null,
                'scheduled_for' => $attributes['scheduled_for'] ?? null,
                'started_at' => $attributes['started_at'] ?? null,
                'finished_at' => $attributes['finished_at'] ?? null,
                'last_error_code' => $attributes['last_error_code'] ?? null,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        } else {
            $this->connection->table('automation_workflow_steps')
                ->where('run_id', $runId)
                ->where('step_key', $stepKey)
                ->update([
                    ...$attributes,
                    'updated_at' => $occurredAt,
                ]);
        }

        /** @var array<string, mixed> $step */
        $step = $this->loadWorkflowStep($runId, $stepKey);

        return $step;
    }

    public function createWorkflowStepAttempt(
        string $runId,
        string $tenantId,
        string $stepKey,
        int $attemptNumber,
        string $attemptKind,
        string $providerToolKey,
        string $idempotencyKey,
        CarbonImmutable $startedAt,
    ): array {
        $this->connection->table('automation_workflow_step_attempts')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'run_id' => $runId,
            'tenant_id' => $tenantId,
            'step_key' => $stepKey,
            'attempt_kind' => $attemptKind,
            'attempt_number' => $attemptNumber,
            'provider_tool_key' => $providerToolKey,
            'idempotency_key' => $idempotencyKey,
            'status' => 'running',
            'started_at' => $startedAt->toIso8601String(),
            'created_at' => $startedAt->toIso8601String(),
            'updated_at' => $startedAt->toIso8601String(),
        ]);

        $attempt = $this->connection->table('automation_workflow_step_attempts')
            ->where('run_id', $runId)
            ->where('step_key', $stepKey)
            ->where('attempt_kind', $attemptKind)
            ->where('attempt_number', $attemptNumber)
            ->first();

        if ($attempt === null) {
            throw new RuntimeException('Workflow step attempt could not be durably created.');
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalize($attempt, ['output']);

        return $normalized;
    }

    public function finishWorkflowStepAttempt(string $attemptId, array $attributes): void
    {
        if (isset($attributes['output']) && is_array($attributes['output'])) {
            $attributes['output'] = json_encode($attributes['output'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $attributes['updated_at'] = CarbonImmutable::now()->toIso8601String();

        $this->connection->table('automation_workflow_step_attempts')
            ->where('id', $attemptId)
            ->update($attributes);
    }

    public function listCompensableCompletedSteps(string $runId): array
    {
        $records = $this->connection->table('automation_workflow_steps')
            ->where('run_id', $runId)
            ->where('status', 'completed')
            ->whereNotNull('compensation_action')
            ->orderByDesc('updated_at')
            ->get();

        return array_values(array_filter(array_map(
            fn (object $record): ?array => $this->normalize($record, ['payload', 'output']),
            $records->all(),
        )));
    }

    public function hasWebhookDelivery(string $tenantId, string $provider, string $externalId): bool
    {
        return $this->connection->table('automation_webhook_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->exists();
    }

    public function recordWebhookDelivery(
        string $tenantId,
        string $actorId,
        string $provider,
        string $market,
        string $version,
        string $capability,
        string $externalId,
        string $payloadHash,
        CarbonImmutable $occurredAt,
    ): bool {
        try {
            $this->connection->table('automation_webhook_deliveries')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'actor_id' => $actorId,
                'provider' => $provider,
                'market' => $market,
                'version' => $version,
                'capability' => $capability,
                'external_id' => $externalId,
                'payload_hash' => $payloadHash,
                'received_at' => $occurredAt->toIso8601String(),
                'created_at' => $occurredAt->toIso8601String(),
                'updated_at' => $occurredAt->toIso8601String(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * @param  object|array<string, mixed>|null  $record
     * @param  list<string>  $jsonColumns
     * @return array<string, mixed>|null
     */
    private function normalize(object|array|null $record, array $jsonColumns = []): ?array
    {
        if ($record === null) {
            return null;
        }

        $data = is_object($record) ? get_object_vars($record) : $record;

        foreach ($jsonColumns as $column) {
            if (! array_key_exists($column, $data) || $data[$column] === null || $data[$column] === '') {
                continue;
            }

            $data[$column] = json_decode((string) $data[$column], true, 512, JSON_THROW_ON_ERROR);
        }

        return $data;
    }
}
