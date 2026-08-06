<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Access\Enums\Permission;
use App\Domain\Ai\Events\AiHandoffChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\LocationScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AiHandoffService
{
    private const TRIGGERS = [
        'explicit_user', 'low_confidence', 'policy', 'safety',
        'provider_failure', 'merchant_rule',
    ];

    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    private const ACTIVE_STATUSES = ['requested', 'queued', 'assigned', 'accepted'];

    public function __construct(
        private AiContentCipher $cipher,
        private TenantPermissionGuardInterface $permissionGuard,
        private OutboxRepositoryInterface $outbox,
        private TenantContext $tenantContext,
        private AiHandoffUniqueViolation $uniqueViolations,
    ) {}

    public function request(RequestAiHandoff $command): string
    {
        $this->validateRequest($command);
        $tenantId = strtolower($command->tenantId);
        $deduplicationKey = hash('sha256', $tenantId.'|'.strtolower($command->conversationId));

        try {
            return DB::transaction(function () use ($command, $tenantId, $deduplicationKey): string {
                $this->assertTenantContext($tenantId);
                $this->lockTenant($tenantId);
                $conversation = DB::table('ai_conversations')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $command->conversationId)
                    ->lockForUpdate()
                    ->first();
                if ($conversation === null) {
                    throw new AiHandoffForbiddenException;
                }
                $messageExists = DB::table('ai_messages')
                    ->where('tenant_id', $tenantId)
                    ->where('conversation_id', $command->conversationId)
                    ->where('id', $command->triggeringMessageId)
                    ->where('role', 'user')
                    ->exists();
                $agentExists = DB::table('ai_agent_versions')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $command->agentVersionId)
                    ->exists();
                if (! $messageExists || ! $agentExists) {
                    throw new AiHandoffForbiddenException;
                }

                $active = DB::table('ai_handoffs')
                    ->where('tenant_id', $tenantId)
                    ->where('conversation_id', $command->conversationId)
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->whereNotNull('active_deduplication_key')
                    ->orderByRaw('CASE WHEN active_deduplication_key IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('requested_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                if ($active !== null) {
                    if (! hash_equals((string) $active->queue_key, $command->queueKey)) {
                        throw new AiHandoffConflictException;
                    }

                    return (string) $active->id;
                }

                $id = (string) Str::uuid();
                $encrypted = $this->cipher->encrypt(
                    $tenantId,
                    $id,
                    'handoff_context',
                    trim($command->contextSummary),
                );
                $now = now();
                DB::table('ai_handoffs')->insert([
                    'id' => $id,
                    'tenant_id' => $tenantId,
                    'conversation_id' => $command->conversationId,
                    'message_id' => $command->triggeringMessageId,
                    'from_type' => 'ai_agent',
                    'to_type' => 'human',
                    'status' => 'requested',
                    'context_ciphertext' => $encrypted->ciphertext,
                    'context_hash' => $encrypted->contentHash,
                    'encryption_key_version' => $encrypted->keyVersion,
                    'trigger_type' => $command->triggerType,
                    'priority' => $command->priority,
                    'queue_key' => $command->queueKey,
                    'active_deduplication_key' => $deduplicationKey,
                    'agent_version_id' => $command->agentVersionId,
                    'version' => 1,
                    'return_to_agent' => false,
                    'requested_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->record($this->locked($tenantId, $id), null);

                DB::table('ai_handoffs')->where('tenant_id', $tenantId)->where('id', $id)->update([
                    'status' => 'queued',
                    'version' => 2,
                    'queued_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->record($this->locked($tenantId, $id), null);

                return $id;
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->uniqueViolations->matches(DB::getDriverName(), $exception)) {
                throw $exception;
            }
            $existing = DB::table('ai_handoffs')
                ->where('tenant_id', $tenantId)
                ->where('active_deduplication_key', $deduplicationKey)
                ->first();
            if ($existing !== null && hash_equals((string) $existing->queue_key, $command->queueKey)) {
                return (string) $existing->id;
            }
            throw new AiHandoffConflictException;
        }
    }

    public function assign(AssignAiHandoff $command): void
    {
        $this->validateMutation($command->tenantId, $command->handoffId, $command->actorId, $command->expectedVersion);
        if (! Str::isUuid($command->assigneeId)) {
            throw new InvalidArgumentException('AI handoff assignee is invalid.');
        }
        DB::transaction(function () use ($command): void {
            $tenant = $this->authorizedTenant($command->tenantId, $command->actorId);
            $row = $this->locked($command->tenantId, $command->handoffId);
            $this->assertVersionAndStatus($row, $command->expectedVersion, ['queued']);
            $this->authorizedActor($tenant, $command->assigneeId);
            $now = now();
            DB::table('ai_handoffs')->where('tenant_id', $command->tenantId)->where('id', $command->handoffId)->update([
                'status' => 'assigned',
                'legacy_status' => null,
                'assigned_to_user_id' => $command->assigneeId,
                'assigned_by_user_id' => $command->actorId,
                'assigned_at' => $now,
                'version' => $command->expectedVersion + 1,
                'updated_at' => $now,
            ]);
            $this->record($this->locked($command->tenantId, $command->handoffId), $command->actorId);
        }, 3);
    }

    public function accept(AcceptAiHandoff $command): void
    {
        $this->validateMutation($command->tenantId, $command->handoffId, $command->actorId, $command->expectedVersion);
        DB::transaction(function () use ($command): void {
            $tenant = $this->authorizedTenant($command->tenantId, $command->actorId);
            $row = $this->locked($command->tenantId, $command->handoffId);
            $this->assertVersionAndStatus($row, $command->expectedVersion, ['assigned']);
            if (! hash_equals((string) $row->assigned_to_user_id, $command->actorId)) {
                throw new AiHandoffForbiddenException;
            }
            $this->authorizedActor($tenant, $command->actorId);
            $now = now();
            DB::table('ai_handoffs')->where('tenant_id', $command->tenantId)->where('id', $command->handoffId)->update([
                'status' => 'accepted',
                'legacy_status' => null,
                'accepted_by_user_id' => $command->actorId,
                'accepted_at' => $now,
                'version' => $command->expectedVersion + 1,
                'updated_at' => $now,
            ]);
            $this->record($this->locked($command->tenantId, $command->handoffId), $command->actorId);
        }, 3);
    }

    public function resolve(ResolveAiHandoff $command): void
    {
        $this->validateMutation($command->tenantId, $command->handoffId, $command->actorId, $command->expectedVersion);
        $note = trim($command->resolutionNote);
        if ($note === '' || mb_strlen($note) > 2_000) {
            throw new InvalidArgumentException('AI handoff resolution note is invalid.');
        }
        DB::transaction(function () use ($command, $note): void {
            $tenant = $this->authorizedTenant($command->tenantId, $command->actorId);
            $row = $this->locked($command->tenantId, $command->handoffId);
            $this->assertVersionAndStatus($row, $command->expectedVersion, ['accepted']);
            if (! hash_equals((string) $row->assigned_to_user_id, $command->actorId)) {
                throw new AiHandoffForbiddenException;
            }
            $this->authorizedActor($tenant, $command->actorId);
            $encrypted = $this->cipher->encrypt(
                strtolower($command->tenantId),
                $command->handoffId,
                'handoff_resolution',
                $note,
            );
            $now = now();
            DB::table('ai_handoffs')->where('tenant_id', $command->tenantId)->where('id', $command->handoffId)->update([
                'status' => 'resolved',
                'legacy_status' => null,
                'resolved_by_user_id' => $command->actorId,
                'resolution_ciphertext' => $encrypted->ciphertext,
                'resolution_hash' => $encrypted->contentHash,
                'resolution_key_version' => $encrypted->keyVersion,
                'return_to_agent' => $command->returnToAgent,
                'active_deduplication_key' => null,
                'resolved_at' => $now,
                'completed_at' => $now,
                'version' => $command->expectedVersion + 1,
                'updated_at' => $now,
            ]);
            $this->record($this->locked($command->tenantId, $command->handoffId), $command->actorId);
        }, 3);
    }

    public function cancel(CancelAiHandoff $command): void
    {
        $this->validateMutation($command->tenantId, $command->handoffId, $command->actorId, $command->expectedVersion);
        DB::transaction(function () use ($command): void {
            $this->authorizedTenant($command->tenantId, $command->actorId);
            $row = $this->locked($command->tenantId, $command->handoffId);
            $this->assertVersionAndStatus($row, $command->expectedVersion, ['requested', 'queued']);
            $now = now();
            DB::table('ai_handoffs')->where('tenant_id', $command->tenantId)->where('id', $command->handoffId)->update([
                'status' => 'cancelled',
                'legacy_status' => null,
                'active_deduplication_key' => null,
                'cancelled_at' => $now,
                'version' => $command->expectedVersion + 1,
                'updated_at' => $now,
            ]);
            $this->record($this->locked($command->tenantId, $command->handoffId), $command->actorId);
        }, 3);
    }

    public function expire(ExpireAiHandoff $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->handoffId)
            || $command->expectedVersion < 1) {
            throw new InvalidArgumentException('AI handoff expiration is invalid.');
        }
        DB::transaction(function () use ($command): void {
            $this->assertTenantContext($command->tenantId);
            $this->lockTenant($command->tenantId);
            $row = $this->locked($command->tenantId, $command->handoffId);
            $this->assertVersionAndStatus($row, $command->expectedVersion, ['requested', 'queued']);
            $now = now();
            DB::table('ai_handoffs')->where('tenant_id', $command->tenantId)->where('id', $command->handoffId)->update([
                'status' => 'expired',
                'legacy_status' => null,
                'active_deduplication_key' => null,
                'expired_at' => $now,
                'version' => $command->expectedVersion + 1,
                'updated_at' => $now,
            ]);
            $this->record($this->locked($command->tenantId, $command->handoffId), null);
        }, 3);
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>} */
    public function queue(string $tenantId, string $actorId, int $page, int $perPage): array
    {
        $this->authorizedTenant($tenantId, $actorId);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $query = DB::table('ai_handoffs')->where('tenant_id', strtolower($tenantId));
        $total = (clone $query)->count();
        $rows = $query->orderBy('queued_at')->orderBy('id')
            ->forPage($page, $perPage)->get();

        return [
            'data' => $rows->map(fn (object $row): array => $this->present($row))->all(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string,mixed> */
    public function show(string $tenantId, string $handoffId, string $actorId): array
    {
        $this->authorizedTenant($tenantId, $actorId);
        $row = DB::table('ai_handoffs')
            ->where('tenant_id', strtolower($tenantId))
            ->where('id', $handoffId)
            ->first();
        if ($row === null) {
            throw new AiHandoffNotFoundException;
        }

        return $this->present($row);
    }

    public function continuationBlock(string $tenantId, string $conversationId): ?AiHandoffContinuationBlock
    {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($conversationId)) {
            throw new InvalidArgumentException('AI handoff continuation identity is invalid.');
        }
        $tenantId = strtolower($tenantId);
        $this->assertTenantContext($tenantId);
        $active = DB::table('ai_handoffs')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', strtolower($conversationId))
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('active_deduplication_key')
            ->orderByRaw('CASE WHEN active_deduplication_key IS NULL THEN 1 ELSE 0 END')
            ->orderBy('requested_at')
            ->orderBy('id')
            ->first(['id', 'status']);
        if ($active !== null) {
            return new AiHandoffContinuationBlock((string) $active->id, (string) $active->status);
        }

        $resolved = DB::table('ai_handoffs')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', strtolower($conversationId))
            ->where('status', 'resolved')
            ->orderByDesc('resolved_at')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->first(['id', 'status', 'return_to_agent']);
        if ($resolved !== null && ! (bool) $resolved->return_to_agent) {
            return new AiHandoffContinuationBlock((string) $resolved->id, (string) $resolved->status);
        }

        return null;
    }

    private function validateRequest(RequestAiHandoff $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->conversationId)
            || ! Str::isUuid($command->triggeringMessageId)
            || ! Str::isUuid($command->agentVersionId)
            || ! in_array($command->triggerType, self::TRIGGERS, true)
            || ! in_array($command->priority, self::PRIORITIES, true)
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $command->queueKey) !== 1
            || mb_strlen($command->queueKey) > 128
            || trim($command->contextSummary) === ''
            || mb_strlen(trim($command->contextSummary)) > 2_000) {
            throw new InvalidArgumentException('AI handoff request is invalid.');
        }
    }

    private function validateMutation(string $tenantId, string $handoffId, string $actorId, int $expectedVersion): void
    {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($handoffId) || ! Str::isUuid($actorId) || $expectedVersion < 1) {
            throw new InvalidArgumentException('AI handoff mutation is invalid.');
        }
    }

    private function assertTenantContext(string $tenantId): void
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            throw new AiHandoffForbiddenException;
        }
        try {
            $contextTenantId = strtolower($this->tenantContext->id());
        } catch (\Throwable) {
            throw new AiHandoffForbiddenException;
        }
        if (! hash_equals($contextTenantId, strtolower($tenantId))) {
            throw new AiHandoffForbiddenException;
        }
    }

    private function lockTenant(string $tenantId): void
    {
        if (DB::table('tenants')->where('id', strtolower($tenantId))->lockForUpdate()->first() === null) {
            throw new AiHandoffForbiddenException;
        }
    }

    private function authorizedTenant(string $tenantId, string $actorId): Tenant
    {
        $tenantId = strtolower($tenantId);
        $this->assertTenantContext($tenantId);
        $this->lockTenant($tenantId);
        // INFRA_TENANT_FILTER: a public city preference cannot override exact authorized tenant identity.
        $tenant = Tenant::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereKey($tenantId)
            ->first();
        if (! $tenant instanceof Tenant) {
            throw new AiHandoffForbiddenException;
        }
        $this->authorizedActor($tenant, $actorId);

        return $tenant;
    }

    private function authorizedActor(Tenant $tenant, string $actorId): User
    {
        $actor = User::query()->find($actorId);
        if (! $actor instanceof User
            || ! $this->permissionGuard->allows($actor, $tenant, Capability::AI_CONTENT, Permission::AI_USE)) {
            throw new AiHandoffForbiddenException;
        }

        return $actor;
    }

    private function locked(string $tenantId, string $handoffId): object
    {
        $row = DB::table('ai_handoffs')
            ->where('tenant_id', strtolower($tenantId))
            ->where('id', $handoffId)
            ->lockForUpdate()
            ->first();
        if ($row === null) {
            throw new AiHandoffNotFoundException;
        }

        return $row;
    }

    /** @param list<string> $statuses */
    private function assertVersionAndStatus(object $row, int $expectedVersion, array $statuses): void
    {
        if ((int) $row->version !== $expectedVersion || ! in_array((string) $row->status, $statuses, true)) {
            throw new AiHandoffConflictException;
        }
    }

    private function record(object $row, ?string $actorId): void
    {
        $safe = [
            'tenant_id' => (string) $row->tenant_id,
            'aggregate_id' => (string) $row->id,
            'conversation_id' => (string) $row->conversation_id,
            'message_id' => (string) $row->message_id,
            'agent_version_id' => (string) $row->agent_version_id,
            'status' => (string) $row->status,
            'version' => (int) $row->version,
            'trigger_type' => (string) $row->trigger_type,
            'queue_key' => (string) $row->queue_key,
            'priority' => (string) $row->priority,
        ];
        $activity = activity('ai-handoff')->withProperties($safe)->event('ai_handoff_changed');
        if ($actorId !== null && ($actor = User::query()->find($actorId)) instanceof User) {
            $activity->causedBy($actor);
        }
        $activity->log('ai_handoff_changed');
        $this->outbox->append(new AiHandoffChanged(
            aggregateId: (string) $row->id,
            tenantId: (string) $row->tenant_id,
            actorId: $actorId,
            conversationId: (string) $row->conversation_id,
            messageId: (string) $row->message_id,
            agentVersionId: (string) $row->agent_version_id,
            status: (string) $row->status,
            version: (int) $row->version,
            triggerType: (string) $row->trigger_type,
            queueKey: (string) $row->queue_key,
            priority: (string) $row->priority,
            occurredAt: now()->toImmutable(),
        ));
    }

    /** @return array<string,mixed> */
    private function present(object $row): array
    {
        $context = null;
        if ($row->context_ciphertext !== null
            && $row->context_hash !== null
            && $row->encryption_key_version !== null) {
            $context = $this->cipher->decrypt(
                (string) $row->tenant_id,
                (string) $row->id,
                'handoff_context',
                new EncryptedAiContent(
                    (string) $row->context_ciphertext,
                    (string) $row->context_hash,
                    (string) $row->encryption_key_version,
                ),
            );
        }
        $resolution = null;
        if ($row->resolution_ciphertext !== null) {
            $resolution = $this->cipher->decrypt(
                (string) $row->tenant_id,
                (string) $row->id,
                'handoff_resolution',
                new EncryptedAiContent(
                    (string) $row->resolution_ciphertext,
                    (string) $row->resolution_hash,
                    (string) $row->resolution_key_version,
                ),
            );
        }

        return [
            'id' => (string) $row->id,
            'conversation_id' => (string) $row->conversation_id,
            'triggering_message_id' => (string) $row->message_id,
            'agent_version_id' => (string) $row->agent_version_id,
            'trigger_type' => (string) $row->trigger_type,
            'priority' => (string) $row->priority,
            'queue_key' => (string) $row->queue_key,
            'status' => (string) $row->status,
            'version' => (int) $row->version,
            'assigned_to_user_id' => $row->assigned_to_user_id === null ? null : (string) $row->assigned_to_user_id,
            'context_summary' => $context === null ? null : mb_substr($context, 0, 2_000),
            'resolution_note' => $resolution === null ? null : mb_substr($resolution, 0, 2_000),
            'return_to_agent' => (bool) $row->return_to_agent,
            'requested_at' => $row->requested_at,
            'queued_at' => $row->queued_at,
            'assigned_at' => $row->assigned_at,
            'accepted_at' => $row->accepted_at,
            'resolved_at' => $row->resolved_at,
        ];
    }
}
