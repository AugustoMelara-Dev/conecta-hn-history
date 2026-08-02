<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final class AiFoundationBackfill
{
    /** @var list<string> */
    private const PHASES = ['expand', 'backfill', 'validate', 'contract'];

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
    ): AiFoundationBackfillReport {
        $limit = min(5_000, max(1, $limit));
        if (! in_array($phase, self::PHASES, true)) {
            throw new InvalidArgumentException('AI foundation backfill phase is invalid.');
        }

        if ($phase === 'expand') {
            return new AiFoundationBackfillReport(
                registryLinked: 0,
                conversationLinked: 0,
                legacyUnresolved: 0,
                retentionClassified: 0,
                gaps: 0,
                conflicts: 0,
                phase: $phase,
                batchChecksum: hash('sha256', 'dovra-ai-foundation|expand'),
                hasMore: false,
                validated: true,
                contractStatus: 'expand_ready',
            );
        }

        $executionCursor = $this->decodeCursor($cursor, 'execution');
        $executionRows = $this->executions($limit, $executionCursor);
        $executions = $executionRows->take($limit)->values();
        $apply = $phase === 'backfill' && ! $dryRun;
        $gaps = 0;
        $conflicts = 0;

        foreach ($executions as $execution) {
            [$executionGaps, $executionConflicts] = DB::transaction(
                fn (): array => $this->backfillExecution(
                    (string) $execution->correlation_id,
                    $apply,
                ),
                3,
            );

            $gaps += $executionGaps;
            $conflicts += $executionConflicts;
        }

        [$retentionClassified, $conversationNextCursor, $conversationHasMore] = $this->classifyConversations(
            $limit,
            $this->decodeCursor($conversationCursor, 'conversation'),
            $apply,
        );

        $executionIds = $executions->pluck('correlation_id')->filter()->values();
        $nextCursor = $executionRows->count() > $limit
            ? $this->encodeCursor($executions->last(), 'execution')
            : null;
        $hasMore = $executionRows->count() > $limit;
        $batchChecksum = $this->batchChecksum($executions, 'execution');

        return new AiFoundationBackfillReport(
            registryLinked: $executionIds->isEmpty()
                ? 0
                : DB::table('ai_executions')
                    ->whereIn('correlation_id', $executionIds->all())
                    ->whereNotNull('provider_version_id')
                    ->whereNotNull('model_version_id')
                    ->count(),
            conversationLinked: $executionIds->isEmpty()
                ? 0
                : DB::table('ai_executions')
                    ->whereIn('correlation_id', $executionIds->all())
                    ->whereNotNull('conversation_id')
                    ->count(),
            legacyUnresolved: $executionIds->isEmpty()
                ? 0
                : DB::table('ai_executions')
                    ->whereIn('correlation_id', $executionIds->all())
                    ->where(function ($query): void {
                        $query
                            ->whereNull('conversation_id')
                            ->orWhereNull('provider_version_id')
                            ->orWhereNull('model_version_id');
                    })
                    ->count(),
            retentionClassified: $retentionClassified,
            gaps: $gaps,
            conflicts: $conflicts,
            phase: $phase,
            batchChecksum: $batchChecksum,
            nextCursor: $nextCursor,
            conversationNextCursor: $conversationNextCursor,
            hasMore: $hasMore || $conversationHasMore,
            validated: $phase === 'validate' && $gaps === 0 && $conflicts === 0,
            contractStatus: $phase === 'contract'
                ? 'deferred_until_consumers_proven'
                : 'no_contract_requested',
        );
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function executions(int $limit, ?array $cursor): \Illuminate\Support\Collection
    {
        $query = DB::table('ai_executions')
            ->orderBy('created_at')
            ->orderBy('correlation_id');

        $this->afterCursor($query, $cursor, 'correlation_id');

        return $query->limit($limit + 1)->get();
    }

    /**
     * @return array{int,?string,bool}
     */
    private function classifyConversations(
        int $limit,
        ?array $cursor,
        bool $apply,
    ): array {
        $retentionDays = max(1, (int) config('ai.retention.conversation_days', 30));
        $query = DB::table('ai_conversations')
            ->orderBy('created_at')
            ->orderBy('id');
        $this->afterCursor($query, $cursor, 'id');
        $rows = $query->limit($limit + 1)->get();
        $conversations = $rows->take($limit)->values();
        $classified = 0;

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
            $rows->count() > $limit ? $this->encodeCursor($conversations->last(), 'conversation') : null,
            $rows->count() > $limit,
        ];
    }

    /** @return array{int,int} */
    private function backfillExecution(string $correlationId, bool $apply): array
    {
        $execution = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->lockForUpdate()
            ->first();

        if ($execution === null) {
            return [1, 0];
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

        return [$gaps, $conflicts];
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

        return [$unique[0] ?? null, $invalid || count($unique) > 1];
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

    /** @param object $row */
    private function encodeCursor(object $row, string $kind): string
    {
        $createdAt = (string) $row->created_at;
        $identifier = (string) ($row->correlation_id ?? $row->id);
        $payload = [
            'kind' => $kind,
            'created_at' => $createdAt,
            'id' => $identifier,
        ];
        $payload['checksum'] = $this->cursorChecksum($payload);

        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{kind:string,created_at:string,id:string}|null */
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

        return [
            'kind' => $expectedKind,
            'created_at' => $payload['created_at'],
            'id' => $payload['id'],
        ];
    }

    private function cursorChecksum(array $payload): string
    {
        return hash('sha256', implode('|', [
            (string) ($payload['kind'] ?? ''),
            (string) ($payload['created_at'] ?? ''),
            (string) ($payload['id'] ?? ''),
        ]));
    }

    private function afterCursor($query, ?array $cursor, string $identifierColumn): void
    {
        if ($cursor === null) {
            return;
        }

        $query->where(function ($builder) use ($cursor, $identifierColumn): void {
            $builder
                ->where('created_at', '>', $cursor['created_at'])
                ->orWhere(function ($sameTimestamp) use ($cursor, $identifierColumn): void {
                    $sameTimestamp
                        ->where('created_at', '=', $cursor['created_at'])
                        ->where($identifierColumn, '>', $cursor['id']);
                });
        });
    }

    private function batchChecksum(iterable $rows, string $kind): string
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'kind' => $kind,
                'created_at' => (string) $row->created_at,
                'id' => (string) ($row->correlation_id ?? $row->id),
            ];
        }

        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
