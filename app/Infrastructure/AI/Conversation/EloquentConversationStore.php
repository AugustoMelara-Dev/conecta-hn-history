<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Conversation;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationContinuationContext;
use App\Application\Ai\Conversation\ConversationEnvelope;
use App\Application\Ai\Conversation\ConversationMessage;
use App\Application\Ai\Conversation\ConversationMessageClaim;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Grounding\EvidenceSnapshot;
use App\Application\Ai\Grounding\GroundingEvidenceProjector;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class EloquentConversationStore implements ConversationStore
{
    /** @var list<string> */
    private const FAILURE_CODES = [
        'ai_temporarily_unavailable',
        'ai_budget_exhausted',
        'ai_rate_limited',
        'ai_configuration_unavailable',
        'ai_response_invalid',
        'ai_evidence_insufficient',
        'ai_handoff_required',
        'ai_request_conflict',
        'ai_access_denied',
    ];

    /** @var array<string, array{table:string,tenant_column:?string}> */
    private const EVIDENCE_RESOURCES = [
        'business' => ['table' => 'tenants', 'tenant_column' => null],
        'product' => ['table' => 'products', 'tenant_column' => 'tenant_id'],
        'service' => ['table' => 'services', 'tenant_column' => 'tenant_id'],
        'order' => ['table' => 'orders', 'tenant_column' => 'tenant_id'],
        'appointment' => ['table' => 'appointments', 'tenant_column' => 'tenant_id'],
        'knowledge_chunk' => ['table' => 'ai_knowledge_chunks', 'tenant_column' => 'tenant_id'],
    ];

    public function __construct(
        private readonly AiContentCipher $cipher,
        private readonly TenantContext $tenantContext,
        private readonly GroundingEvidenceProjector $groundingEvidence,
    ) {}

    public function claim(AcceptConversationMessage $command): ConversationMessageClaim
    {
        $envelope = ConversationEnvelope::fromCommand($command);
        $this->assertTenantAccess($envelope->tenantId, $envelope->visibility);
        $this->assertEnvelope($envelope);

        $conversationId = $envelope->conversationId ?? $this->stableId(match (true) {
            $envelope->externalThreadHash !== null => "conversation-thread|{$envelope->tenantId}|{$envelope->externalThreadHash}",
            $envelope->visibility === 'platform_public' && $envelope->actorId !== null => "conversation-public|{$envelope->tenantId}|{$envelope->actorId}|{$envelope->idempotencyKey}",
            default => "conversation-new|{$envelope->tenantId}|{$envelope->idempotencyKey}",
        });
        $messageId = $this->stableId(
            "conversation-request|{$envelope->tenantId}|{$conversationId}|{$envelope->idempotencyKey}",
        );
        $payloadHash = $this->hash($envelope->canonicalPayload($conversationId));

        try {
            return DB::transaction(function () use (
                $conversationId,
                $envelope,
                $messageId,
                $payloadHash,
            ): ConversationMessageClaim {
                $existingMessage = DB::table('ai_messages')
                    ->where('tenant_id', $envelope->tenantId)
                    ->where('conversation_id', $conversationId)
                    ->where('id', $messageId)
                    ->lockForUpdate()
                    ->first();
                if ($existingMessage !== null) {
                    return $this->replayedClaim($envelope->tenantId, $existingMessage, $payloadHash);
                }

                if ($envelope->conversationId === null) {
                    DB::table('ai_conversations')->insertOrIgnore([
                        'id' => $conversationId,
                        'tenant_id' => $envelope->tenantId,
                        'visibility' => $envelope->visibility,
                        'origin_type' => $envelope->originType,
                        'external_thread_hash' => $envelope->externalThreadHash,
                        'actor_id' => $envelope->actorId,
                        'locale' => $envelope->locale,
                        'market_country_code' => $envelope->marketCountryCode,
                        'currency' => $envelope->currency,
                        'timezone' => $envelope->timezone,
                        'status' => 'active',
                        'retention_category' => 'conversation_standard',
                        'retention_expires_at' => now()->addDays(
                            max(1, (int) config('ai.execution_retention.days', 30)),
                        ),
                        'last_message_sequence' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $conversation = DB::table('ai_conversations')
                    ->where('id', $conversationId)
                    ->where('tenant_id', $envelope->tenantId)
                    ->lockForUpdate()
                    ->first();
                if ($conversation === null) {
                    $this->deny();
                }
                $this->assertOpenConversation($conversation);
                $this->assertConversationEnvelope($conversation, $envelope);

                $existingMessage = DB::table('ai_messages')
                    ->where('tenant_id', $envelope->tenantId)
                    ->where('conversation_id', $conversationId)
                    ->where('id', $messageId)
                    ->lockForUpdate()
                    ->first();
                if ($existingMessage !== null) {
                    return $this->replayedClaim($envelope->tenantId, $existingMessage, $payloadHash);
                }

                $sequence = ((int) $conversation->last_message_sequence) + 1;
                $encrypted = $this->cipher->encrypt(
                    tenantId: $envelope->tenantId,
                    recordId: $messageId,
                    contentClass: 'conversation_message',
                    plaintext: $envelope->content,
                );
                DB::table('ai_messages')->insert([
                    'id' => $messageId,
                    'tenant_id' => $envelope->tenantId,
                    'conversation_id' => $conversationId,
                    'sequence' => $sequence,
                    'direction' => 'inbound',
                    'role' => 'user',
                    'content_ciphertext' => $encrypted->ciphertext,
                    'content_hash' => $encrypted->contentHash,
                    'encryption_key_version' => $encrypted->keyVersion,
                    'content_type' => $envelope->contentType,
                    'idempotency_key' => $envelope->idempotencyKey,
                    'idempotency_hash' => $payloadHash,
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ai_conversations')->where('id', $conversationId)->update([
                    'last_message_sequence' => $sequence,
                    'updated_at' => now(),
                ]);

                $message = DB::table('ai_messages')->where('id', $messageId)->first();
                if ($message === null) {
                    throw new RuntimeException('AI conversation message could not be persisted.');
                }

                return new ConversationMessageClaim(
                    conversationId: $conversationId,
                    requestMessage: $this->message($message),
                    replayed: false,
                    response: null,
                );
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->claimAfterRace(
                $envelope->tenantId,
                $conversationId,
                $messageId,
                $payloadHash,
            );
        }
    }

    public function findActiveTenantPrivate(
        string $tenantId,
        string $conversationId,
    ): ?ConversationContinuationContext {
        $tenantId = strtolower(trim($tenantId));
        $this->assertTenantAccess($tenantId, 'tenant_private');
        if (! Str::isUuid($conversationId)) {
            $this->deny();
        }

        $conversation = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->where('visibility', 'tenant_private')
            ->where('status', 'active')
            ->first([
                'tenant_id',
                'id',
                'actor_id',
                'origin_type',
                'external_thread_hash',
                'locale',
                'market_country_code',
                'currency',
                'timezone',
            ]);
        if ($conversation === null || $conversation->actor_id === null) {
            return null;
        }

        return new ConversationContinuationContext(
            tenantId: (string) $conversation->tenant_id,
            conversationId: (string) $conversation->id,
            actorId: (string) $conversation->actor_id,
            originType: (string) $conversation->origin_type,
            externalThreadHash: $conversation->external_thread_hash === null
                ? null
                : (string) $conversation->external_thread_hash,
            locale: (string) $conversation->locale,
            marketCountryCode: (string) $conversation->market_country_code,
            currency: (string) $conversation->currency,
            timezone: (string) $conversation->timezone,
        );
    }

    public function completeAssistantMessage(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        AiRuntimeSelection $selection,
        ?AiExecutionResult $result,
        array $answer,
        array $evidence,
    ): ConversationMessage {
        $tenantId = strtolower($tenantId);
        $this->assertTenantAccess($tenantId);
        $this->assertIdentifiers($tenantId, $conversationId, $requestMessageId);
        $normalizedAnswer = $this->normalizeAnswer($answer);
        $validatedEvidence = $this->normalizeEvidence($tenantId, $requestMessageId, $evidence);
        $responseId = $this->responseId($tenantId, $requestMessageId);
        $answerJson = $this->json($normalizedAnswer);
        $completionHash = $this->hash([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'answer' => $normalizedAnswer,
            'evidence' => $validatedEvidence,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'execution_id' => $result?->correlationId,
        ]);

        try {
            return DB::transaction(function () use (
                $tenantId,
                $conversationId,
                $requestMessageId,
                $selection,
                $result,
                $normalizedAnswer,
                $validatedEvidence,
                $responseId,
                $answerJson,
                $completionHash,
            ): ConversationMessage {
                $conversation = $this->lockedConversation($tenantId, $conversationId);
                $request = $this->lockedRequest($tenantId, $conversationId, $requestMessageId);
                $execution = $this->lockedExecutionLineage(
                    $tenantId,
                    $conversationId,
                    $requestMessageId,
                    $responseId,
                    $selection,
                    $result,
                );
                $this->lockEvidenceResources($tenantId, $validatedEvidence);
                $existing = DB::table('ai_messages')
                    ->where('id', $responseId)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    $this->assertResponseReplay($existing, $completionHash, 'completed');

                    return $this->message($existing);
                }

                $sequence = ((int) $conversation->last_message_sequence) + 1;
                $encrypted = $this->cipher->encrypt(
                    tenantId: $tenantId,
                    recordId: $responseId,
                    contentClass: 'conversation_message',
                    plaintext: $answerJson,
                );
                DB::table('ai_messages')->insert([
                    'id' => $responseId,
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'sequence' => $sequence,
                    'direction' => 'outbound',
                    'role' => 'assistant',
                    'content_ciphertext' => $encrypted->ciphertext,
                    'content_hash' => $encrypted->contentHash,
                    'encryption_key_version' => $encrypted->keyVersion,
                    'content_type' => 'application/json',
                    'idempotency_key' => "response:{$requestMessageId}",
                    'idempotency_hash' => $completionHash,
                    'status' => 'completed',
                    'agent_version_id' => $selection->agentVersionId,
                    'prompt_version_id' => $selection->promptVersionId,
                    'provider_version_id' => $selection->providerVersionId,
                    'model_version_id' => $selection->modelVersionId,
                    'execution_id' => $result?->correlationId,
                    'accepted_at' => $request->accepted_at,
                    'completed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($validatedEvidence as $index => $item) {
                    DB::table('ai_message_evidence')->insert([
                        'id' => $item['row_id'] ?? $this->stableId("evidence|{$responseId}|{$index}"),
                        'tenant_id' => $tenantId,
                        'message_id' => $responseId,
                        'evidence_type' => $item['evidence_type'],
                        'resource_type' => $item['resource_type'],
                        'resource_id' => $item['resource_id'],
                        'content_hash' => $item['content_hash'],
                        'metadata' => null,
                        'evidence_id' => $item['evidence_id'] ?? null,
                        'source_type' => $item['source_type'] ?? null,
                        'source_id' => $item['source_id'] ?? null,
                        'source_version' => $item['source_version'] ?? null,
                        'source_tenant_id' => $item['source_tenant_id'] ?? null,
                        'visibility' => $item['visibility'] ?? null,
                        'citation_label' => $item['citation_label'] ?? null,
                        'fresh_at' => $item['fresh_at'] ?? null,
                        'retrieval_rank' => $item['retrieval_rank'] ?? null,
                        'retrieval_score' => $item['retrieval_score'] ?? null,
                        'currency' => $item['currency'] ?? null,
                        'price_minor' => $item['price_minor'] ?? null,
                        'availability' => $item['availability'] ?? null,
                        'excerpt_ciphertext' => $item['excerpt_ciphertext'] ?? null,
                        'excerpt_hash' => $item['excerpt_hash'] ?? null,
                        'excerpt_encryption_key_version' => $item['excerpt_encryption_key_version'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('ai_conversations')->where('id', $conversationId)->update([
                    'last_message_sequence' => $sequence,
                    'agent_version_id' => $selection->agentVersionId,
                    'updated_at' => now(),
                ]);
                $updated = DB::table('ai_executions')
                    ->where('correlation_id', $execution->correlation_id)
                    ->where('tenant_id', $tenantId)
                    ->where('conversation_id', $conversationId)
                    ->where('request_message_id', $requestMessageId)
                    ->whereNull('response_message_id')
                    ->update([
                        'response_message_id' => $responseId,
                        'updated_at' => now(),
                    ]);
                if ($updated !== 1) {
                    throw new RuntimeException('AI execution conversation linkage could not be persisted.');
                }

                $stored = DB::table('ai_messages')->where('id', $responseId)->first();
                if ($stored === null) {
                    throw new RuntimeException('AI conversation response could not be persisted.');
                }

                return new ConversationMessage(
                    id: $responseId,
                    tenantId: $tenantId,
                    conversationId: $conversationId,
                    sequence: $sequence,
                    direction: 'outbound',
                    role: 'assistant',
                    content: $normalizedAnswer,
                    contentType: 'application/json',
                    status: 'completed',
                    agentVersionId: $selection->agentVersionId,
                    promptVersionId: $selection->promptVersionId,
                    providerVersionId: $selection->providerVersionId,
                    modelVersionId: $selection->modelVersionId,
                    executionId: $result?->correlationId,
                );
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
            $existing = DB::table('ai_messages')
                ->where('tenant_id', $tenantId)
                ->where('id', $responseId)
                ->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertResponseReplay($existing, $completionHash, 'completed');

            return $this->message($existing);
        }
    }

    public function failAssistantMessage(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        string $publicCode,
        bool $retryable,
        array $fallback = [],
    ): void {
        if (! in_array($publicCode, self::FAILURE_CODES, true)) {
            throw new InvalidArgumentException('AI conversation failure code is invalid.');
        }
        $tenantId = strtolower($tenantId);
        $this->assertTenantAccess($tenantId);
        $this->assertIdentifiers($tenantId, $conversationId, $requestMessageId);
        $normalizedFallback = $fallback === [] ? [] : $this->normalizeAnswer($fallback);
        $fallbackJson = $normalizedFallback === [] ? null : $this->json($normalizedFallback);
        $responseId = $this->responseId($tenantId, $requestMessageId);
        $failureHash = $this->hash([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'failure_code' => $publicCode,
            'retryable' => $retryable,
            'fallback' => $normalizedFallback,
        ]);

        try {
            DB::transaction(function () use (
                $tenantId,
                $conversationId,
                $requestMessageId,
                $publicCode,
                $retryable,
                $responseId,
                $failureHash,
                $fallbackJson,
            ): void {
                $conversation = $this->lockedConversation($tenantId, $conversationId);
                $request = $this->lockedRequest($tenantId, $conversationId, $requestMessageId);
                $existing = DB::table('ai_messages')
                    ->where('id', $responseId)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    $this->assertResponseReplay($existing, $failureHash, 'failed');
                    $failure = DB::table('ai_message_failures')
                        ->where('tenant_id', $tenantId)
                        ->where('message_id', $responseId)
                        ->first();
                    if ($failure === null
                        || ! hash_equals((string) $failure->failure_code, $publicCode)
                        || (bool) $failure->retryable !== $retryable
                        || $failure->failure_detail !== null) {
                        throw new AiConversationConflictException;
                    }

                    return;
                }

                $sequence = ((int) $conversation->last_message_sequence) + 1;
                $encrypted = $fallbackJson === null ? null : $this->cipher->encrypt(
                    tenantId: $tenantId,
                    recordId: $responseId,
                    contentClass: 'conversation_message',
                    plaintext: $fallbackJson,
                );
                DB::table('ai_messages')->insert([
                    'id' => $responseId,
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'sequence' => $sequence,
                    'direction' => 'outbound',
                    'role' => 'assistant',
                    'content_ciphertext' => $encrypted?->ciphertext,
                    'content_hash' => $encrypted?->contentHash,
                    'encryption_key_version' => $encrypted?->keyVersion,
                    'content_type' => 'application/json',
                    'idempotency_key' => "response:{$requestMessageId}",
                    'idempotency_hash' => $failureHash,
                    'status' => 'failed',
                    'accepted_at' => $request->accepted_at,
                    'failed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ai_message_failures')->insert([
                    'id' => $this->stableId("failure|{$responseId}"),
                    'tenant_id' => $tenantId,
                    'message_id' => $responseId,
                    'failure_code' => $publicCode,
                    'failure_detail' => null,
                    'retryable' => $retryable,
                    'attempt' => 1,
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ai_conversations')->where('id', $conversationId)->update([
                    'last_message_sequence' => $sequence,
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
            $existing = DB::table('ai_messages')
                ->where('tenant_id', $tenantId)
                ->where('id', $responseId)
                ->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertResponseReplay($existing, $failureHash, 'failed');
        }
    }

    private function claimAfterRace(
        string $tenantId,
        string $conversationId,
        string $messageId,
        string $payloadHash,
    ): ConversationMessageClaim {
        $existing = DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->where('id', $messageId)
            ->first();
        if ($existing === null) {
            throw new RuntimeException('AI conversation race could not be reconciled.');
        }

        return $this->replayedClaim($tenantId, $existing, $payloadHash);
    }

    private function replayedClaim(
        string $tenantId,
        object $existing,
        string $payloadHash,
    ): ConversationMessageClaim {
        if ((string) $existing->tenant_id !== $tenantId
            || ! hash_equals((string) $existing->idempotency_hash, $payloadHash)
            || (string) $existing->role !== 'user') {
            throw new AiConversationConflictException;
        }
        $conversation = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $existing->conversation_id)
            ->first();
        if ($conversation === null || (string) $conversation->status !== 'active') {
            $this->deny();
        }
        $response = DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $existing->conversation_id)
            ->where('id', $this->responseId($tenantId, (string) $existing->id))
            ->first();

        return new ConversationMessageClaim(
            conversationId: (string) $existing->conversation_id,
            requestMessage: $this->message($existing),
            replayed: true,
            response: $response === null ? null : $this->message($response),
        );
    }

    private function message(object $row): ConversationMessage
    {
        $content = null;
        if ($row->content_ciphertext !== null) {
            $plaintext = $this->cipher->decrypt(
                tenantId: (string) $row->tenant_id,
                recordId: (string) $row->id,
                contentClass: 'conversation_message',
                content: new EncryptedAiContent(
                    ciphertext: (string) $row->content_ciphertext,
                    contentHash: (string) $row->content_hash,
                    keyVersion: (string) $row->encryption_key_version,
                ),
            );
            if ((string) $row->content_type === 'application/json') {
                $decoded = json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new RuntimeException('AI conversation response payload is invalid.');
                }
                $content = $decoded;
            } else {
                $content = $plaintext;
            }
        }

        return new ConversationMessage(
            id: (string) $row->id,
            tenantId: (string) $row->tenant_id,
            conversationId: (string) $row->conversation_id,
            sequence: (int) $row->sequence,
            direction: (string) $row->direction,
            role: (string) $row->role,
            content: $content,
            contentType: (string) $row->content_type,
            status: (string) $row->status,
            agentVersionId: $row->agent_version_id === null ? null : (string) $row->agent_version_id,
            promptVersionId: $row->prompt_version_id === null ? null : (string) $row->prompt_version_id,
            providerVersionId: $row->provider_version_id === null ? null : (string) $row->provider_version_id,
            modelVersionId: $row->model_version_id === null ? null : (string) $row->model_version_id,
            executionId: $row->execution_id === null ? null : (string) $row->execution_id,
        );
    }

    private function assertTenantAccess(string $tenantId, ?string $visibility = null): void
    {
        if (! Str::isUuid($tenantId)
            || ! $this->tenantContext->isInitialized()
            || $this->tenantContext->isRoot()) {
            $this->deny();
        }
        try {
            $contextTenantId = $this->tenantContext->id();
        } catch (\Throwable) {
            $this->deny();
        }
        if (! hash_equals(strtolower($contextTenantId), strtolower($tenantId))) {
            $this->deny();
        }
        if ($visibility === 'platform_public') {
            $platformTenantId = config('ai.public.billing_tenant_id');
            if (! is_string($platformTenantId)
                || ! Str::isUuid($platformTenantId)
                || ! hash_equals(strtolower($platformTenantId), strtolower($tenantId))) {
                $this->deny();
            }
        }
        if (! DB::table('tenants')->where('id', $tenantId)->exists()) {
            $this->deny();
        }
    }

    private function assertEnvelope(ConversationEnvelope $envelope): void
    {
        if (! in_array($envelope->visibility, ['tenant_private', 'platform_public'], true)
            || ! in_array($envelope->originType, ['web', 'api', 'mobile', 'whatsapp'], true)
            || ! in_array($envelope->contentType, ['text', 'markdown'], true)
            || ! preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $envelope->locale)
            || ! preg_match('/^[A-Z]{2}$/', $envelope->marketCountryCode)
            || ! preg_match('/^[A-Z]{3}$/', $envelope->currency)
            || ! in_array($envelope->timezone, timezone_identifiers_list(), true)
            || ($envelope->visibility === 'tenant_private' && $envelope->actorId === null)
            || ! DB::table('countries')->where('code', $envelope->marketCountryCode)->where('is_active', true)->exists()
            || ! DB::table('locales')->where('code', $envelope->locale)->where('is_active', true)->exists()
            || ! DB::table('currencies')->where('code', $envelope->currency)->where('is_active', true)->exists()
            || ! DB::table('timezones')->where('name', $envelope->timezone)->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('AI conversation envelope is invalid.');
        }
    }

    private function assertConversationEnvelope(object $conversation, ConversationEnvelope $envelope): void
    {
        foreach ([
            'tenant_id' => $envelope->tenantId,
            'visibility' => $envelope->visibility,
            'origin_type' => $envelope->originType,
            'external_thread_hash' => $envelope->externalThreadHash,
            'actor_id' => $envelope->actorId,
            'locale' => $envelope->locale,
            'market_country_code' => $envelope->marketCountryCode,
            'currency' => $envelope->currency,
            'timezone' => $envelope->timezone,
        ] as $field => $expected) {
            $actual = $conversation->{$field};
            if (($actual === null ? null : (string) $actual) !== $expected) {
                throw new AiConversationConflictException;
            }
        }
    }

    private function assertOpenConversation(object $conversation): void
    {
        if ((string) $conversation->status !== 'active') {
            $this->deny();
        }
    }

    private function lockedConversation(string $tenantId, string $conversationId): object
    {
        $conversation = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->lockForUpdate()
            ->first();
        if ($conversation === null) {
            $this->deny();
        }
        $this->assertOpenConversation($conversation);
        if ((string) $conversation->visibility === 'platform_public') {
            $this->assertTenantAccess($tenantId, 'platform_public');
        }

        return $conversation;
    }

    private function lockedRequest(string $tenantId, string $conversationId, string $requestMessageId): object
    {
        $request = DB::table('ai_messages')
            ->where('tenant_id', $tenantId)
            ->where('conversation_id', $conversationId)
            ->where('id', $requestMessageId)
            ->where('role', 'user')
            ->lockForUpdate()
            ->first();
        if ($request === null) {
            $this->deny();
        }

        return $request;
    }

    private function assertIdentifiers(string $tenantId, string $conversationId, string $requestMessageId): void
    {
        if (! Str::isUuid($tenantId)
            || ! Str::isUuid($conversationId)
            || ! Str::isUuid($requestMessageId)) {
            $this->deny();
        }
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>
     */
    private function normalizeAnswer(array $answer): array
    {
        if ($answer === [] || array_is_list($answer)) {
            throw new InvalidArgumentException('AI conversation answer is invalid.');
        }
        $normalized = $this->normalizeJsonValue($answer);
        if (! is_array($normalized)) {
            throw new InvalidArgumentException('AI conversation answer is invalid.');
        }

        return $normalized;
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $item) {
                if (! is_int($key) && ! is_string($key)) {
                    throw new InvalidArgumentException('AI conversation answer is invalid.');
                }
                $value[$key] = $this->normalizeJsonValue($item);
            }

            return $value;
        }
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        throw new InvalidArgumentException('AI conversation answer is invalid.');
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @return list<array<string, mixed>>
     */
    private function normalizeEvidence(string $tenantId, string $requestMessageId, array $evidence): array
    {
        if (! array_is_list($evidence) || count($evidence) > 20) {
            throw new InvalidArgumentException('AI conversation evidence is invalid.');
        }
        $validated = [];
        $seen = [];
        foreach ($evidence as $item) {
            if ($item instanceof EvidenceSnapshot) {
                $rowId = GroundingEvidenceProjector::rowId(
                    $tenantId,
                    $requestMessageId,
                    $item->evidenceId,
                );
                if ($item->tenantId !== $tenantId || isset($seen[$item->evidenceId])) {
                    throw new InvalidArgumentException('AI conversation evidence is invalid.');
                }
                $seen[$item->evidenceId] = true;
                $this->cipher->decrypt(
                    $tenantId,
                    $rowId,
                    'evidence_excerpt',
                    $item->excerpt,
                );
                $validated[] = [
                    'snapshot' => true,
                    'row_id' => $rowId,
                    'tenant_id' => $tenantId,
                    'evidence_type' => 'snapshot',
                    'resource_type' => $item->sourceType,
                    'resource_id' => $item->sourceId,
                    'content_hash' => $item->excerpt->contentHash,
                    'evidence_id' => $item->evidenceId,
                    'source_type' => $item->sourceType,
                    'source_id' => $item->sourceId,
                    'source_version' => $item->sourceVersion,
                    'source_tenant_id' => $item->sourceTenantId,
                    'visibility' => $item->visibility,
                    'citation_label' => $item->citationLabel,
                    'fresh_at' => $item->freshAt->utc()->format('Y-m-d H:i:s.u'),
                    'retrieval_rank' => $item->retrievalRank,
                    'retrieval_score' => $item->retrievalScore,
                    'currency' => $item->currency,
                    'price_minor' => $item->priceMinor,
                    'availability' => $item->availability,
                    'excerpt_ciphertext' => $item->excerpt->ciphertext,
                    'excerpt_hash' => $item->excerpt->contentHash,
                    'excerpt_encryption_key_version' => $item->excerpt->keyVersion,
                ];

                continue;
            }
            if (! is_array($item)
                || array_diff(array_keys($item), [
                    'evidence_type',
                    'resource_type',
                    'resource_id',
                    'source_tenant_id',
                    'content_hash',
                ]) !== []
                || ($item['evidence_type'] ?? null) !== 'resource'
                || ! is_string($item['resource_type'] ?? null)
                || ! isset(self::EVIDENCE_RESOURCES[$item['resource_type']])
                || ! is_string($item['resource_id'] ?? null)
                || trim($item['resource_id']) === ''
                || mb_strlen($item['resource_id']) > 64
                || ($item['source_tenant_id'] ?? null) !== $tenantId
                || (isset($item['content_hash'])
                    && (! is_string($item['content_hash'])
                        || preg_match('/^[a-f0-9]{64}$/', $item['content_hash']) !== 1))) {
                throw new InvalidArgumentException('AI conversation evidence is invalid.');
            }
            $validated[] = [
                'snapshot' => false,
                'evidence_type' => 'resource',
                'resource_type' => $item['resource_type'],
                'resource_id' => $item['resource_id'],
                'source_tenant_id' => $tenantId,
                'content_hash' => $item['content_hash'] ?? null,
            ];
        }

        return $validated;
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     */
    private function lockEvidenceResources(string $tenantId, array $evidence): void
    {
        $this->groundingEvidence->lockAndVerify($evidence);
        $resources = [];
        foreach ($evidence as $item) {
            if (($item['snapshot'] ?? false) === true) {
                continue;
            }
            $key = $item['resource_type'].'|'.$item['resource_id'];
            $resources[$key] = $item;
        }
        ksort($resources, SORT_STRING);

        foreach ($resources as $item) {
            $resource = self::EVIDENCE_RESOURCES[$item['resource_type']];
            $query = DB::table($resource['table'])->where('id', $item['resource_id']);
            if ($resource['tenant_column'] === null) {
                $query->where('id', $tenantId);
            } else {
                $query->where($resource['tenant_column'], $tenantId);
            }
            if ($query->lockForUpdate()->first(['id']) === null) {
                throw new InvalidArgumentException('AI conversation evidence is invalid.');
            }
        }
    }

    private function lockedExecutionLineage(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        string $responseMessageId,
        AiRuntimeSelection $selection,
        ?AiExecutionResult $result,
    ): object {
        if ($result === null
            || ! Str::isUuid($result->correlationId)
            || $result->provider !== $selection->providerKey
            || $result->model !== $selection->modelKey) {
            $this->deny();
        }
        $execution = DB::table('ai_executions')
            ->where('correlation_id', $result->correlationId)
            ->lockForUpdate()
            ->first();
        if ($execution === null
            || (string) $execution->tenant_id !== $tenantId
            || (string) $execution->conversation_id !== $conversationId
            || (string) $execution->request_message_id !== $requestMessageId
            || (string) $execution->status !== 'completed'
            || $execution->completed_at === null
            || (string) $execution->provider !== $selection->providerKey
            || (string) $execution->provider !== $result->provider
            || (string) $execution->model !== $selection->modelKey
            || (string) $execution->model !== $result->model
            || (string) $execution->provider_version_id !== $selection->providerVersionId
            || (string) $execution->model_version_id !== $selection->modelVersionId
            || (string) $execution->agent_version_id !== $selection->agentVersionId
            || (string) $execution->prompt_version_id !== $selection->promptVersionId
            || ($execution->response_message_id !== null
                && (string) $execution->response_message_id !== $responseMessageId)) {
            $this->deny();
        }

        return $execution;
    }

    private function assertResponseReplay(object $existing, string $hash, string $status): void
    {
        if ((string) $existing->role !== 'assistant'
            || (string) $existing->status !== $status
            || ! hash_equals((string) $existing->idempotency_hash, $hash)) {
            throw new AiConversationConflictException;
        }
    }

    private function responseId(string $tenantId, string $requestMessageId): string
    {
        return $this->stableId("conversation-response|{$tenantId}|{$requestMessageId}");
    }

    private function stableId(string $value): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, "https://dovra.app/ai/{$value}")->toString();
    }

    private function hash(array $payload): string
    {
        return hash('sha256', $this->json($payload));
    }

    private function json(array $payload): string
    {
        try {
            return json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('AI conversation payload is invalid.');
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $driver = DB::connection($exception->getConnectionName())->getDriverName();

        if ($driver === 'pgsql') {
            return $sqlState === '23505';
        }

        if ($driver === 'mysql') {
            return $sqlState === '23000' && $driverCode === 1062;
        }

        if ($driver !== 'sqlite' || $sqlState !== '23000') {
            return false;
        }

        $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        return in_array($driverCode, [19, 1555, 2067], true)
            && preg_match('/(?:UNIQUE constraint failed|PRIMARY KEY must be unique|is not unique)/i', $detail) === 1;
    }

    private function deny(): never
    {
        throw new AiExecutionDeniedException('ai_access_denied');
    }
}
