<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final class AiFoundationBackfill
{
    /** @var list<string> */
    private const PHASES = ['expand', 'backfill', 'validate', 'contract'];

    /**
     * Persist the first resolved agent path for a conversation atomically.
     * Runtime controllers may request this canonical write, but they do not
     * own conversation-table mutation.
     */
    public function persistCanaryPath(
        string $tenantId,
        string $conversationId,
        string $agentVersionId,
    ): ?object {
        if (! Str::isUuid($tenantId)
            || ! Str::isUuid($conversationId)
            || ! Str::isUuid($agentVersionId)) {
            throw new InvalidArgumentException('AI canary path identifiers are invalid.');
        }

        return DB::transaction(function () use ($tenantId, $conversationId, $agentVersionId): ?object {
            $conversation = DB::table('ai_conversations')
                ->where('tenant_id', $tenantId)
                ->where('id', $conversationId)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                return null;
            }

            if ($conversation->agent_version_id === null) {
                DB::table('ai_conversations')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $conversationId)
                    ->whereNull('agent_version_id')
                    ->update([
                        'agent_version_id' => $agentVersionId,
                        'updated_at' => now(),
                    ]);

                $conversation->agent_version_id = $agentVersionId;
            }

            return $conversation;
        }, 3);
    }

    /**
     * Read the canonical sticky agent assignment for a conversation.
     * A non-null value is intentionally returned without coercion so the
     * registry resolver can fail closed when legacy data is malformed.
     */
    public function pinnedAgentVersionId(string $tenantId, string $conversationId): ?string
    {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($conversationId)) {
            return null;
        }

        $value = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->value('agent_version_id');

        return $value === null ? null : (string) $value;
    }

    /**
     * Run one bounded, deterministic batch.
     *
     * The cursor is an opaque, integrity-checked tuple of created_at and the
     * stable row identifier. It lets operators resume after a partial failure
     * without reordering a growing table. The legacy one-argument invocation
     * remains valid and intentionally replays the first bounded batch so the
     * operation stays idempotent for existing callers.
     */
    public function run(
        int $limit,
        ?string $cursor = null,
        string $phase = 'backfill',
        bool $dryRun = false,
        ?string $conversationCursor = null,
        ?array $executionSnapshot = null,
        ?array $conversationSnapshot = null,
    ): AiFoundationBackfillReport {
        $limit = min(5_000, max(1, $limit));
        if (! in_array($phase, self::PHASES, true)) {
            throw new InvalidArgumentException('AI foundation backfill phase is invalid.');
        }

        if ($phase === 'expand') {
            $missingSchema = $this->missingExpandSchema();

            return new AiFoundationBackfillReport(
                registryLinked: 0,
                conversationLinked: 0,
                legacyUnresolved: 0,
                retentionClassified: 0,
                gaps: count($missingSchema),
                conflicts: 0,
                phase: $phase,
                batchChecksum: hash('sha256', json_encode([
                    'phase' => 'expand',
                    'missing_schema' => $missingSchema,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                hasMore: false,
                validated: $missingSchema === [],
                contractStatus: $missingSchema === [] ? 'expand_ready' : 'expand_missing_schema',
            );
        }

        $executionCursor = $this->decodeCursor($cursor, 'execution');
        [$executionRows, $executionSnapshot] = $this->executions(
            $limit,
            $executionCursor,
            $executionSnapshot ?? $executionCursor['snapshot'] ?? null,
        );
        $executions = $executionRows->take($limit)->values();
        $apply = $phase === 'backfill' && ! $dryRun;
        $gaps = 0;
        $conflicts = 0;
        $registryLinked = 0;
        $conversationLinked = 0;
        $legacyUnresolved = 0;

        foreach ($executions as $execution) {
            [
                $executionGaps,
                $executionConflicts,
                $executionRegistryLinked,
                $executionConversationLinked,
                $executionUnresolved,
            ] = DB::transaction(
                fn (): array => $this->backfillExecution(
                    (string) $execution->correlation_id,
                    $apply,
                ),
                3,
            );

            $gaps += $executionGaps;
            $conflicts += $executionConflicts;
            $registryLinked += (int) $executionRegistryLinked;
            $conversationLinked += (int) $executionConversationLinked;
            $legacyUnresolved += (int) $executionUnresolved;
        }

        [
            $retentionClassified,
            $conversationNextCursor,
            $conversationHasMore,
            $conversationSnapshot,
            $conversationChecksum,
        ] = $this->classifyConversations(
            $limit,
            $this->decodeCursor($conversationCursor, 'conversation'),
            $apply,
            $conversationSnapshot,
        );

        $nextCursor = $executionRows->count() > $limit
            ? $this->encodeCursor($executions->last(), 'execution', $executionSnapshot)
            : null;
        $hasMore = $executionRows->count() > $limit;
        $batchChecksum = hash('sha256', json_encode([
            'executions' => $this->batchChecksum($executions, 'execution'),
            'conversations' => $conversationChecksum,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return new AiFoundationBackfillReport(
            registryLinked: $registryLinked,
            conversationLinked: $conversationLinked,
            legacyUnresolved: $legacyUnresolved,
            retentionClassified: $retentionClassified,
            gaps: $gaps,
            conflicts: $conflicts,
            phase: $phase,
            batchChecksum: $batchChecksum,
            nextCursor: $nextCursor,
            conversationNextCursor: $conversationNextCursor,
            hasMore: $hasMore || $conversationHasMore,
            validated: $phase === 'validate'
                && $gaps === 0
                && $conflicts === 0
                && ! ($hasMore || $conversationHasMore),
            contractStatus: $phase === 'contract'
                ? 'deferred_until_consumers_proven'
                : 'no_contract_requested',
            sourceSnapshot: [
                'executions' => $executionSnapshot,
                'conversations' => $conversationSnapshot,
            ],
        );
    }

    /** @return array{\Illuminate\Support\Collection<int, object>,array{created_at:string,id:string,empty?:bool}} */
    private function executions(int $limit, ?array $cursor, ?array $snapshot = null): array
    {
        $query = DB::table('ai_executions as backfill_executions')
            ->leftJoin('ai_messages as backfill_request', function ($join): void {
                $join
                    ->on('backfill_request.tenant_id', '=', 'backfill_executions.tenant_id')
                    ->on('backfill_request.id', '=', 'backfill_executions.request_message_id');
            })
            ->leftJoin('ai_messages as backfill_response', function ($join): void {
                $join
                    ->on('backfill_response.tenant_id', '=', 'backfill_executions.tenant_id')
                    ->on('backfill_response.id', '=', 'backfill_executions.response_message_id');
            })
            ->select('backfill_executions.*')
            ->addSelect([
                'backfill_request.conversation_id as request_conversation_id',
                'backfill_request.provider_version_id as request_provider_version_id',
                'backfill_request.model_version_id as request_model_version_id',
                'backfill_request.agent_version_id as request_agent_version_id',
                'backfill_request.prompt_version_id as request_prompt_version_id',
                'backfill_response.conversation_id as response_conversation_id',
                'backfill_response.provider_version_id as response_provider_version_id',
                'backfill_response.model_version_id as response_model_version_id',
                'backfill_response.agent_version_id as response_agent_version_id',
                'backfill_response.prompt_version_id as response_prompt_version_id',
            ])
            ->orderBy('backfill_executions.created_at')
            ->orderBy('backfill_executions.correlation_id');

        $snapshot = $snapshot ?? $cursor['snapshot'] ?? $this->snapshotBoundary('ai_executions', 'correlation_id');
        $this->afterCursor($query, $cursor, 'backfill_executions.correlation_id', 'backfill_executions.created_at');
        $this->withinSnapshot($query, $snapshot, 'backfill_executions.correlation_id', 'backfill_executions.created_at');

        return [$query->limit($limit + 1)->get(), $snapshot];
    }

    /**
     * @return array{int,?string,bool,array{created_at:string,id:string,empty?:bool},string}
     */
    private function classifyConversations(
        int $limit,
        ?array $cursor,
        bool $apply,
        ?array $snapshot = null,
    ): array {
        $retentionDays = max(1, (int) config('ai.retention.conversation_days', 30));
        $query = DB::table('ai_conversations')
            ->orderBy('created_at')
            ->orderBy('id');
        $snapshot = $snapshot ?? $cursor['snapshot'] ?? $this->snapshotBoundary('ai_conversations', 'id');
        $this->afterCursor($query, $cursor, 'id', 'created_at');
        $this->withinSnapshot($query, $snapshot, 'id', 'created_at');
        $rows = $query->limit($limit + 1)->get();
        $conversations = $rows->take($limit)->values();
        $classified = 0;
        $conversationChecksum = $this->batchChecksum($conversations, 'conversation');

        foreach ($conversations as $conversation) {
            $classified++;
            $updates = [];

            if (! is_string($conversation->retention_category)
                || trim($conversation->retention_category) === '') {
                $updates['retention_category'] = 'conversation_standard';
            }

            if ($conversation->retention_expires_at === null) {
                $anchor = $conversation->updated_at ?? $conversation->created_at ?? now();
                $updates['retention_expires_at'] = Carbon::parse((string) $anchor)->addDays($retentionDays);
            }

            if ($apply && $updates !== []) {
                $updates['updated_at'] = now();
                DB::table('ai_conversations')
                    ->where('id', $conversation->id)
                    ->where('tenant_id', $conversation->tenant_id)
                    ->update($updates);
            }

            if ($apply && is_string($conversation->actor_id) && $conversation->actor_id !== '') {
                DB::table('ai_conversation_participants')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => (string) $conversation->tenant_id,
                    'conversation_id' => (string) $conversation->id,
                    'participant_type' => 'actor',
                    'participant_id' => (string) $conversation->actor_id,
                    'role' => 'owner',
                    'joined_at' => $conversation->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return [
            $classified,
            $rows->count() > $limit
                ? $this->encodeCursor($conversations->last(), 'conversation', $snapshot)
                : null,
            $rows->count() > $limit,
            $snapshot,
            $conversationChecksum,
        ];
    }

    /** @return array{int,int,bool,bool,bool} */
    private function backfillExecution(string $correlationId, bool $apply): array
    {
        $execution = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->lockForUpdate()
            ->first();

        if ($execution === null) {
            return [1, 0, false, false, true];
        }

        $tenantId = (string) $execution->tenant_id;
        $request = $this->message($tenantId, $execution->request_message_id);
        $response = $this->message($tenantId, $execution->response_message_id);
        $gaps = 0;
        $conflicts = 0;
        $updates = [];

        [$agentVersionId, $agentConflict] = $this->consistentUuid([
            $execution->agent_version_id,
            $response?->agent_version_id,
            $request?->agent_version_id,
        ]);
        [$promptVersionId, $promptConflict] = $this->consistentUuid([
            $execution->prompt_version_id,
            $response?->prompt_version_id,
            $request?->prompt_version_id,
        ]);
        [$providerVersionId, $providerConflict] = $this->consistentUuid([
            $execution->provider_version_id,
            $response?->provider_version_id,
            $request?->provider_version_id,
        ]);
        [$modelVersionId, $modelConflict] = $this->consistentUuid([
            $execution->model_version_id,
            $response?->model_version_id,
            $request?->model_version_id,
        ]);
        $conflicts += (int) ($agentConflict || $promptConflict || $providerConflict || $modelConflict);

        $registryLineageValid = false;
        if ($providerVersionId === null || $modelVersionId === null) {
            $gaps++;
        } else {
            $registryLineageValid = $this->registryLineageIsValid(
                $tenantId,
                $execution,
                $providerVersionId,
                $modelVersionId,
                $agentVersionId,
                $promptVersionId,
            );
            if (! $registryLineageValid) {
                $conflicts++;
            } elseif ($apply) {
                $updates['provider_version_id'] = $providerVersionId;
                $updates['model_version_id'] = $modelVersionId;
            }
        }

        if ($agentVersionId !== null && $promptVersionId !== null && $registryLineageValid && $apply) {
            $updates['agent_version_id'] = $agentVersionId;
            $updates['prompt_version_id'] = $promptVersionId;
        } elseif ($agentVersionId === null || $promptVersionId === null) {
            $gaps++;
        }

        [$conversationId, $conversationGap, $conversationConflict] = $this->resolveConversation(
            $tenantId,
            $execution,
            $request,
            $response,
        );
        $gaps += $conversationGap;
        $conflicts += $conversationConflict;
        if ($conversationId !== null && $apply) {
            $updates['conversation_id'] = $conversationId;
        }

        if ($apply && $updates !== []) {
            $updates['updated_at'] = now();
            DB::table('ai_executions')
                ->where('correlation_id', $correlationId)
                ->where('tenant_id', $tenantId)
                ->update($updates);
        }

        $registryLinked = $registryLineageValid
            && ($apply
                || ($providerVersionId !== null
                    && $modelVersionId !== null
                    && $agentVersionId !== null
                    && $promptVersionId !== null
                    && hash_equals((string) $execution->provider_version_id, $providerVersionId)
                    && hash_equals((string) $execution->model_version_id, $modelVersionId)
                    && hash_equals((string) $execution->agent_version_id, $agentVersionId)
                    && hash_equals((string) $execution->prompt_version_id, $promptVersionId)));
        $conversationLinked = $conversationId !== null
            && ($apply || hash_equals((string) $execution->conversation_id, $conversationId));

        return [
            $gaps,
            $conflicts,
            $registryLinked,
            $conversationLinked,
            ! ($registryLinked && $conversationLinked),
        ];
    }

    private function registryLineageIsValid(
        string $tenantId,
        object $execution,
        string $providerVersionId,
        string $modelVersionId,
        ?string $agentVersionId,
        ?string $promptVersionId,
    ): bool {
        $provider = DB::table('ai_provider_versions')
            ->join('ai_providers', 'ai_providers.id', '=', 'ai_provider_versions.provider_id')
            ->where('ai_provider_versions.id', $providerVersionId)
            ->select([
                'ai_provider_versions.provider_id',
                'ai_providers.provider_key',
            ])
            ->first();
        $model = DB::table('ai_model_versions')
            ->join('ai_models', 'ai_models.id', '=', 'ai_model_versions.model_id')
            ->where('ai_model_versions.id', $modelVersionId)
            ->select([
                'ai_model_versions.provider_version_id',
                'ai_models.provider_id',
                'ai_models.key',
            ])
            ->first();

        if ($provider === null || $model === null
            || (string) $model->provider_version_id !== $providerVersionId
            || (string) $model->provider_id !== (string) $provider->provider_id
            || ($execution->provider !== null
                && ! hash_equals((string) $execution->provider, (string) $provider->provider_key))
            || ($execution->model !== null
                && ! hash_equals((string) $execution->model, (string) $model->key))) {
            return false;
        }

        if ($agentVersionId === null || $promptVersionId === null) {
            return false;
        }

        $agent = DB::table('ai_agent_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $agentVersionId)
            ->first();
        $prompt = DB::table('ai_prompt_versions')
            ->where('tenant_id', $tenantId)
            ->where('id', $promptVersionId)
            ->first();
        if ($agent === null || $prompt === null) {
            return false;
        }

        $configuration = $this->jsonObject($agent->configuration ?? null);
        $configuredPromptId = $configuration['prompt_version_id'] ?? null;
        $configuredModelIds = $configuration['model_version_ids'] ?? null;

        // Older execution rows may predate the registry configuration payload.
        // When those optional declarations exist, they are authoritative and
        // must agree; their absence is recorded by the legacy counters rather
        // than being treated as permission to invent a different lineage.
        if ($configuredPromptId !== null
            && (! is_string($configuredPromptId) || ! hash_equals($configuredPromptId, $promptVersionId))) {
            return false;
        }
        if ($configuredModelIds !== null
            && (! is_array($configuredModelIds)
                || ! in_array($modelVersionId, array_map('strval', $configuredModelIds), true))) {
            return false;
        }

        return true;
    }

    /** @return array{?string,int,int} */
    private function resolveConversation(
        string $tenantId,
        object $execution,
        ?object $request,
        ?object $response,
    ): array {
        [$conversationId, $idConflict] = $this->consistentUuid([
            $execution->conversation_id,
            $request?->conversation_id,
            $response?->conversation_id,
        ]);
        if ($idConflict) {
            return [null, 0, 1];
        }

        foreach ([$request, $response] as $message) {
            if ($message === null || $message->execution_id === null) {
                continue;
            }
            if (! hash_equals((string) $message->execution_id, (string) $execution->correlation_id)) {
                return [null, 0, 1];
            }
        }

        if ($conversationId === null) {
            return [null, 1, 0];
        }

        $exists = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->exists();
        if (! $exists) {
            return [null, 0, 1];
        }

        return [$conversationId, 0, 0];
    }

    private function message(string $tenantId, mixed $messageId): ?object
    {
        if (! is_string($messageId) || ! Str::isUuid($messageId)) {
            return null;
        }

        return DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->first();
    }

    /** @param array<int,mixed> $values @return array{?string,bool} */
    private function consistentUuid(array $values): array
    {
        $ids = [];
        $invalid = false;
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_string($value) || ! Str::isUuid($value)) {
                $invalid = true;
                continue;
            }
            $ids[] = $value;
        }
        $unique = array_values(array_unique($ids));

        if ($invalid || count($unique) > 1) {
            return [null, true];
        }

        return [$unique[0] ?? null, false];
    }

    /** @return array<string,mixed> */
    private function jsonObject(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : [];
    }

    /** @param object $row @param array{created_at:string,id:string}|null $snapshot */
    private function encodeCursor(object $row, string $kind, ?array $snapshot = null): string
    {
        $createdAt = (string) $row->created_at;
        $identifier = (string) ($row->correlation_id ?? $row->id);
        $payload = [
            'kind' => $kind,
            'created_at' => $createdAt,
            'id' => $identifier,
        ];
        if ($snapshot !== null) {
            $payload['snapshot'] = [
                'created_at' => (string) $snapshot['created_at'],
                'id' => (string) $snapshot['id'],
            ];
            if (($snapshot['empty'] ?? false) === true) {
                $payload['snapshot']['empty'] = true;
            }
        }
        $payload['checksum'] = $this->cursorChecksum($payload);

        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{kind:string,created_at:string,id:string,snapshot:?array{created_at:string,id:string,empty?:bool}}|null */
    private function decodeCursor(?string $cursor, string $expectedKind): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('AI foundation backfill cursor is invalid.');
        }
        try {
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('AI foundation backfill cursor is invalid.');
        }

        if (! is_array($payload)
            || ($payload['kind'] ?? null) !== $expectedKind
            || ! is_string($payload['created_at'] ?? null)
            || ! is_string($payload['id'] ?? null)
            || ! is_string($payload['checksum'] ?? null)
            || ! hash_equals($payload['checksum'], $this->cursorChecksum($payload))) {
            throw new InvalidArgumentException('AI foundation backfill cursor checksum is invalid.');
        }

        $snapshot = null;
        if (isset($payload['snapshot'])) {
            $rawSnapshot = $payload['snapshot'];
            $emptySnapshot = is_array($rawSnapshot) ? ($rawSnapshot['empty'] ?? false) : false;
            if (! is_array($rawSnapshot)
                || ! is_bool($emptySnapshot)
                || ! is_string($rawSnapshot['created_at'] ?? null)
                || ! is_string($rawSnapshot['id'] ?? null)
                || ($emptySnapshot
                    && ($rawSnapshot['created_at'] !== '' || $rawSnapshot['id'] !== ''))
                || (! $emptySnapshot
                    && ($rawSnapshot['created_at'] === '' || $rawSnapshot['id'] === ''))) {
                throw new InvalidArgumentException('AI foundation backfill cursor snapshot is invalid.');
            }
            $snapshot = [
                'created_at' => $rawSnapshot['created_at'],
                'id' => $rawSnapshot['id'],
            ];
            if ($emptySnapshot) {
                $snapshot['empty'] = true;
            }
        }

        return [
            'kind' => $expectedKind,
            'created_at' => $payload['created_at'],
            'id' => $payload['id'],
            'snapshot' => $snapshot,
        ];
    }

    private function cursorChecksum(array $payload): string
    {
        $snapshot = is_array($payload['snapshot'] ?? null) ? $payload['snapshot'] : [];

        return hash('sha256', implode('|', [
            (string) ($payload['kind'] ?? ''),
            (string) ($payload['created_at'] ?? ''),
            (string) ($payload['id'] ?? ''),
            (string) ($snapshot['created_at'] ?? ''),
            (string) ($snapshot['id'] ?? ''),
            ($snapshot['empty'] ?? false) === true ? 'empty' : 'bounded',
        ]));
    }

    private function afterCursor($query, ?array $cursor, string $identifierColumn, string $createdAtColumn = 'created_at'): void
    {
        if ($cursor === null) {
            return;
        }

        $query->where(function ($builder) use ($cursor, $identifierColumn, $createdAtColumn): void {
            $builder
                ->where($createdAtColumn, '>', $cursor['created_at'])
                ->orWhere(function ($sameTimestamp) use ($cursor, $identifierColumn, $createdAtColumn): void {
                    $sameTimestamp
                        ->where($createdAtColumn, '=', $cursor['created_at'])
                        ->where($identifierColumn, '>', $cursor['id']);
                });
        });
    }

    /** @param array{created_at:string,id:string,empty?:bool}|null $snapshot */
    private function withinSnapshot($query, ?array $snapshot, string $identifierColumn, string $createdAtColumn = 'created_at'): void
    {
        if ($snapshot === null) {
            return;
        }
        if (($snapshot['empty'] ?? false) === true) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($builder) use ($snapshot, $identifierColumn, $createdAtColumn): void {
            $builder
                ->where($createdAtColumn, '<', $snapshot['created_at'])
                ->orWhere(function ($sameTimestamp) use ($snapshot, $identifierColumn, $createdAtColumn): void {
                    $sameTimestamp
                        ->where($createdAtColumn, '=', $snapshot['created_at'])
                        ->where($identifierColumn, '<=', $snapshot['id']);
                });
        });
    }

    /** @return array{created_at:string,id:string,empty?:bool} */
    private function snapshotBoundary(string $table, string $identifierColumn): array
    {
        $row = DB::table($table)
            ->orderByDesc('created_at')
            ->orderByDesc($identifierColumn)
            ->first(['created_at', $identifierColumn]);

        if ($row === null) {
            return [
                'created_at' => '',
                'id' => '',
                'empty' => true,
            ];
        }

        return [
            'created_at' => (string) $row->created_at,
            'id' => (string) $row->{$identifierColumn},
        ];
    }

    private function batchChecksum(iterable $rows, string $kind): string
    {
        $items = [];
        foreach ($rows as $row) {
            if ($kind === 'execution') {
                $item = [
                    'kind' => $kind,
                    'tenant_id' => $row->tenant_id ?? null,
                    'correlation_id' => $row->correlation_id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'provider' => $row->provider ?? null,
                    'model' => $row->model ?? null,
                    'request_message_id' => $row->request_message_id ?? null,
                    'response_message_id' => $row->response_message_id ?? null,
                    'lineage' => [
                        'conversation' => $this->lineageValues([
                            $row->conversation_id ?? null,
                            $row->request_conversation_id ?? null,
                            $row->response_conversation_id ?? null,
                        ]),
                        'provider_version' => $this->lineageValues([
                            $row->provider_version_id ?? null,
                            $row->request_provider_version_id ?? null,
                            $row->response_provider_version_id ?? null,
                        ]),
                        'model_version' => $this->lineageValues([
                            $row->model_version_id ?? null,
                            $row->request_model_version_id ?? null,
                            $row->response_model_version_id ?? null,
                        ]),
                        'agent_version' => $this->lineageValues([
                            $row->agent_version_id ?? null,
                            $row->request_agent_version_id ?? null,
                            $row->response_agent_version_id ?? null,
                        ]),
                        'prompt_version' => $this->lineageValues([
                            $row->prompt_version_id ?? null,
                            $row->request_prompt_version_id ?? null,
                            $row->response_prompt_version_id ?? null,
                        ]),
                    ],
                ];
            } else {
                $item = [
                    'kind' => $kind,
                    'tenant_id' => $row->tenant_id ?? null,
                    'id' => $row->id ?? null,
                    'created_at' => $row->created_at ?? null,
                    'visibility' => $row->visibility ?? null,
                    'origin_type' => $row->origin_type ?? null,
                    'external_thread_hash' => $row->external_thread_hash ?? null,
                    'actor_id' => $row->actor_id ?? null,
                    'locale' => $row->locale ?? null,
                    'market_country_code' => $row->market_country_code ?? null,
                    'currency' => $row->currency ?? null,
                    'timezone' => $row->timezone ?? null,
                ];
            }
            $items[] = $item;
        }

        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<int,mixed> $values @return list<string> */
    private function lineageValues(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    /** @return list<string> */
    private function missingExpandSchema(): array
    {
        $required = [
            'ai_executions' => [
                'correlation_id', 'tenant_id', 'provider_version_id', 'model_version_id',
                'agent_version_id', 'prompt_version_id', 'conversation_id',
            ],
            'ai_conversations' => [
                'id', 'tenant_id', 'agent_version_id', 'retention_category', 'retention_expires_at',
            ],
            'ai_conversation_participants' => [
                'id', 'tenant_id', 'conversation_id', 'participant_id',
            ],
        ];
        $missing = [];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }

        return $missing;
    }
}
