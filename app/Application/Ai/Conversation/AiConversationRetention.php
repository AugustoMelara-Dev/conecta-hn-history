<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiConversationRetention
{
    public function prune(int $limit): AiRetentionReport
    {
        $conversationLimit = min(5_000, max(1, $limit));
        $report = [
            'conversationsPruned' => 0,
            'messagesRedacted' => 0,
            'evidenceRedacted' => 0,
            'providerPayloadsPruned' => 0,
            'legalHoldsSkipped' => 0,
            'failures' => [],
        ];

        foreach ($this->expiredConversationBatch($conversationLimit) as $conversation) {
            try {
                DB::transaction(function () use ($conversation, &$report): void {
                    $lockedConversationQuery = DB::table('ai_conversations')
                        ->where('id', $conversation->id)
                        ->where('tenant_id', $conversation->tenant_id)
                        ->whereNotNull('retention_expires_at')
                        ->where('retention_expires_at', '<=', now());

                    if (DB::getDriverName() === 'pgsql') {
                        $lockedConversationQuery->lockForUpdate();
                    }

                    $lockedConversation = $lockedConversationQuery->first();

                    if ($lockedConversation === null) {
                        return;
                    }

                    if ($this->hasActiveLegalHold((string) $lockedConversation->tenant_id, (string) $lockedConversation->id)) {
                        $report['legalHoldsSkipped']++;

                        return;
                    }

                    $messageIds = DB::table('ai_messages')
                        ->where('tenant_id', $lockedConversation->tenant_id)
                        ->where('conversation_id', $lockedConversation->id)
                        ->orderBy('sequence')
                        ->pluck('id');

                    $messagesRedacted = $messageIds->isEmpty()
                        ? 0
                        : DB::table('ai_messages')
                            ->where('tenant_id', $lockedConversation->tenant_id)
                            ->whereIn('id', $messageIds->all())
                            ->whereNotNull('content_ciphertext')
                            ->update([
                                'content_ciphertext' => null,
                                'encryption_key_version' => null,
                                'updated_at' => now()->utc(),
                            ]);

                    $evidenceRedacted = 0;

                    if ($messageIds->isNotEmpty()) {
                        $evidenceRows = DB::table('ai_message_evidence')
                            ->where('tenant_id', $lockedConversation->tenant_id)
                            ->whereIn('message_id', $messageIds->all())
                            ->get(['id', 'metadata', 'excerpt_ciphertext', 'excerpt_encryption_key_version']);

                        foreach ($evidenceRows as $evidenceRow) {
                            $sanitizedMetadata = $this->sanitizeEvidenceMetadata($evidenceRow->metadata);
                            $metadataChanged = $sanitizedMetadata !== $evidenceRow->metadata;
                            $excerptPresent = $evidenceRow->excerpt_ciphertext !== null
                                || $evidenceRow->excerpt_encryption_key_version !== null;

                            if (! $metadataChanged && ! $excerptPresent) {
                                continue;
                            }

                            DB::table('ai_message_evidence')
                                ->where('id', $evidenceRow->id)
                                ->update([
                                    'metadata' => $sanitizedMetadata,
                                    'excerpt_ciphertext' => null,
                                    'excerpt_encryption_key_version' => null,
                                    'updated_at' => now()->utc(),
                                ]);

                            $evidenceRedacted++;
                        }
                    }

                    $providerPayloadsPruned = DB::table('ai_executions')
                        ->leftJoin('ai_credit_reservations', 'ai_credit_reservations.id', '=', 'ai_executions.reservation_id')
                        ->where('ai_executions.tenant_id', $lockedConversation->tenant_id)
                        ->where('ai_executions.conversation_id', $lockedConversation->id)
                        ->whereIn('ai_executions.status', ['completed', 'failed'])
                        ->whereNotNull('ai_executions.result_ciphertext')
                        ->whereNull('ai_executions.payload_pruned_at')
                        ->where(static function ($query): void {
                            $query->whereNull('ai_executions.reservation_id')
                                ->orWhereIn('ai_credit_reservations.status', ['settled', 'refunded']);
                        })
                        ->update([
                            'result_ciphertext' => null,
                            'result_hash' => null,
                            'payload_pruned_at' => now()->utc(),
                            'updated_at' => now()->utc(),
                        ]);

                    if ($messagesRedacted > 0 || $evidenceRedacted > 0 || $providerPayloadsPruned > 0) {
                        $report['conversationsPruned']++;
                    }

                    $report['messagesRedacted'] += $messagesRedacted;
                    $report['evidenceRedacted'] += $evidenceRedacted;
                    $report['providerPayloadsPruned'] += $providerPayloadsPruned;
                }, 3);
            } catch (Throwable $exception) {
                $report['failures'][] = 'conversation:'.(string) $conversation->id;
            }
        }

        return new AiRetentionReport(
            conversationsPruned: $report['conversationsPruned'],
            messagesRedacted: $report['messagesRedacted'],
            evidenceRedacted: $report['evidenceRedacted'],
            providerPayloadsPruned: $report['providerPayloadsPruned'],
            legalHoldsSkipped: $report['legalHoldsSkipped'],
            failures: $report['failures'],
        );
    }

    public function backlogCount(): int
    {
        return $this->expiredConversationCandidates(skipLegalHolds: true)->count();
    }

    protected function expiredConversationBatch(int $limit): Collection
    {
        return $this->expiredConversationCandidates(skipLegalHolds: false)
            ->select('ai_conversations.id', 'ai_conversations.tenant_id')
            ->orderBy('ai_conversations.retention_expires_at')
            ->limit($limit)
            ->get();
    }

    protected function expiredConversationCandidates(bool $skipLegalHolds): Builder
    {
        $query = DB::table('ai_conversations')
            ->whereNotNull('ai_conversations.retention_expires_at')
            ->where('ai_conversations.retention_expires_at', '<=', now())
            ->where(function ($candidateQuery): void {
                $candidateQuery
                    ->whereExists(function ($messageQuery): void {
                        $messageQuery->selectRaw('1')
                            ->from('ai_messages')
                            ->whereColumn('ai_messages.conversation_id', 'ai_conversations.id')
                            ->whereColumn('ai_messages.tenant_id', 'ai_conversations.tenant_id')
                            ->whereNotNull('ai_messages.content_ciphertext');
                    })
                    ->orWhereExists(function ($evidenceQuery): void {
                        $evidenceQuery->selectRaw('1')
                            ->from('ai_message_evidence')
                            ->join('ai_messages', function ($join): void {
                                $join->on('ai_messages.id', '=', 'ai_message_evidence.message_id')
                                    ->on('ai_messages.tenant_id', '=', 'ai_message_evidence.tenant_id');
                            })
                            ->whereColumn('ai_messages.conversation_id', 'ai_conversations.id')
                            ->whereColumn('ai_messages.tenant_id', 'ai_conversations.tenant_id')
                            ->whereNotNull('ai_message_evidence.excerpt_ciphertext');
                    })
                    ->orWhereExists(function ($executionQuery): void {
                        $executionQuery->selectRaw('1')
                            ->from('ai_executions')
                            ->leftJoin('ai_credit_reservations', 'ai_credit_reservations.id', '=', 'ai_executions.reservation_id')
                            ->whereColumn('ai_executions.conversation_id', 'ai_conversations.id')
                            ->whereColumn('ai_executions.tenant_id', 'ai_conversations.tenant_id')
                            ->whereIn('ai_executions.status', ['completed', 'failed'])
                            ->whereNotNull('ai_executions.result_ciphertext')
                            ->whereNull('ai_executions.payload_pruned_at')
                            ->where(static function ($query): void {
                                $query->whereNull('ai_executions.reservation_id')
                                    ->orWhereIn('ai_credit_reservations.status', ['settled', 'refunded']);
                            });
                    });
            });

        if ($skipLegalHolds) {
            $query->whereNotExists(function ($holdQuery): void {
                $holdQuery->selectRaw('1')
                    ->from('dovra_legal_holds')
                    ->whereColumn('dovra_legal_holds.tenant_id', 'ai_conversations.tenant_id')
                    ->whereColumn('dovra_legal_holds.subject_id', 'ai_conversations.id')
                    ->where('dovra_legal_holds.subject_type', 'ai_conversation')
                    ->where('dovra_legal_holds.status', 'active')
                    ->whereNull('dovra_legal_holds.released_at');
            });
        }

        return $query;
    }

    protected function hasActiveLegalHold(string $tenantId, string $conversationId): bool
    {
        return DB::table('dovra_legal_holds')
            ->where('tenant_id', $tenantId)
            ->where('subject_type', 'ai_conversation')
            ->where('subject_id', $conversationId)
            ->where('status', 'active')
            ->whereNull('released_at')
            ->exists();
    }

    private function sanitizeEvidenceMetadata(mixed $metadata): ?string
    {
        if (! is_string($metadata) || trim($metadata) === '') {
            return $metadata === null ? null : (string) $metadata;
        }

        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $metadata;
        }

        if (! is_array($decoded)) {
            return $metadata;
        }

        unset($decoded['excerpt'], $decoded['prompt'], $decoded['provider_payload']);

        return json_encode($decoded, JSON_THROW_ON_ERROR);
    }
}
