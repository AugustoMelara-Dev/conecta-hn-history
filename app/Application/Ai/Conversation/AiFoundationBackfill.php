<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiFoundationBackfill
{
    public function run(int $limit): AiFoundationBackfillReport
    {
        $limit = min(5_000, max(1, $limit));

        $executions = DB::table('ai_executions')
            ->orderBy('created_at')
            ->orderBy('correlation_id')
            ->limit($limit)
            ->get();

        $gaps = 0;
        $conflicts = 0;

        foreach ($executions as $execution) {
            [$executionGaps, $executionConflicts] = DB::transaction(
                fn (): array => $this->backfillExecution((string) $execution->correlation_id),
                3,
            );

            $gaps += $executionGaps;
            $conflicts += $executionConflicts;
        }

        $this->classifyConversations($limit);

        $conversationIds = DB::table('ai_conversations')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $executionIds = $executions->pluck('correlation_id')->filter()->values();

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
            retentionClassified: $conversationIds->isEmpty()
                ? 0
                : DB::table('ai_conversations')
                    ->whereIn('id', $conversationIds->all())
                    ->whereNotNull('retention_category')
                    ->where('retention_category', '<>', '')
                    ->count(),
            gaps: $gaps,
            conflicts: $conflicts,
        );
    }

    /** @return array{int,int} */
    private function backfillExecution(string $correlationId): array
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

        $agentVersionId = $this->firstUuid([
            $execution->agent_version_id,
            $response?->agent_version_id,
            $request?->agent_version_id,
        ]);
        $promptVersionId = $this->firstUuid([
            $execution->prompt_version_id,
            $response?->prompt_version_id,
            $request?->prompt_version_id,
        ]);
        $providerVersionId = $this->firstUuid([
            $execution->provider_version_id,
            $response?->provider_version_id,
            $request?->provider_version_id,
        ]);
        $modelVersionId = $this->firstUuid([
            $execution->model_version_id,
            $response?->model_version_id,
            $request?->model_version_id,
        ]);

        if ($providerVersionId === null || $modelVersionId === null) {
            $gaps++;
        } else {
            $providerKey = DB::table('ai_provider_versions')
                ->join('ai_providers', 'ai_providers.id', '=', 'ai_provider_versions.provider_id')
                ->where('ai_provider_versions.id', $providerVersionId)
                ->value('ai_providers.provider_key');
            $modelKey = DB::table('ai_model_versions')
                ->join('ai_models', 'ai_models.id', '=', 'ai_model_versions.model_id')
                ->where('ai_model_versions.id', $modelVersionId)
                ->value('ai_models.key');

            if ((! is_string($providerKey) || $providerKey === '')
                || (! is_string($modelKey) || $modelKey === '')
                || ($execution->provider !== null && ! hash_equals((string) $execution->provider, $providerKey))
                || ($execution->model !== null && ! hash_equals((string) $execution->model, $modelKey))) {
                $conflicts++;
            } else {
                $updates['provider_version_id'] = $providerVersionId;
                $updates['model_version_id'] = $modelVersionId;
            }
        }

        if ($agentVersionId !== null) {
            $updates['agent_version_id'] = $agentVersionId;
        }

        if ($promptVersionId !== null) {
            $updates['prompt_version_id'] = $promptVersionId;
        }

        $conversationId = $this->deterministicConversationId($tenantId, $execution, $request, $response);
        if ($conversationId === null) {
            if ($request !== null || $response !== null) {
                $conflicts++;
            } else {
                $gaps++;
            }
        } else {
            $updates['conversation_id'] = $conversationId;
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            DB::table('ai_executions')
                ->where('correlation_id', $correlationId)
                ->update($updates);
        }

        return [$gaps, $conflicts];
    }

    private function classifyConversations(int $limit): void
    {
        $retentionDays = max(1, (int) config('ai.retention.conversation_days', 30));

        DB::table('ai_conversations')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (object $conversation) use ($retentionDays): void {
                $updates = [];

                if (! is_string($conversation->retention_category) || trim($conversation->retention_category) === '') {
                    $updates['retention_category'] = 'conversation_standard';
                }

                if ($conversation->retention_expires_at === null) {
                    $anchor = $conversation->updated_at ?? $conversation->created_at ?? now();
                    $updates['retention_expires_at'] = Carbon::parse((string) $anchor)->addDays($retentionDays);
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    DB::table('ai_conversations')
                        ->where('id', $conversation->id)
                        ->where('tenant_id', $conversation->tenant_id)
                        ->update($updates);
                }

                if (is_string($conversation->actor_id) && $conversation->actor_id !== '') {
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
            });
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

    private function deterministicConversationId(
        string $tenantId,
        object $execution,
        ?object $request,
        ?object $response,
    ): ?string {
        if (is_string($execution->conversation_id) && Str::isUuid($execution->conversation_id)) {
            return $execution->conversation_id;
        }

        $requestConversationId = $request !== null && is_string($request->conversation_id)
            ? (string) $request->conversation_id
            : null;
        $responseConversationId = $response !== null && is_string($response->conversation_id)
            ? (string) $response->conversation_id
            : null;

        if ($requestConversationId !== null
            && $responseConversationId !== null
            && hash_equals($requestConversationId, $responseConversationId)
            && DB::table('ai_conversations')
                ->where('tenant_id', $tenantId)
                ->where('id', $requestConversationId)
                ->exists()) {
            return $requestConversationId;
        }

        return null;
    }

    /** @param  array<int, mixed>  $values */
    private function firstUuid(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && Str::isUuid($value)) {
                return $value;
            }
        }

        return null;
    }
}
