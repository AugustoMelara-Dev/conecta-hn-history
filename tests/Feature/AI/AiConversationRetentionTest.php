<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AiConversationRetention;
use App\Application\Ai\Conversation\AiRetentionReport;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class AiConversationRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_redacts_expired_conversation_payloads_skips_legal_holds_and_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);

        $expired = $this->conversation((string) $tenant->id, status: 'active', retentionExpiresAt: now()->subHour());
        $held = $this->conversation((string) $tenant->id, status: 'closed', retentionExpiresAt: now()->subHour());
        $active = $this->conversation((string) $tenant->id, status: 'active', retentionExpiresAt: now()->addDay());

        [$expiredUserMessageId, $expiredAssistantMessageId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $expired,
            selection: $selection,
            executionId: null,
        );
        $expiredExecutionId = (string) Str::uuid();
        [$heldUserMessageId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $held,
            selection: $selection,
            executionId: null,
        );
        $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $active,
            selection: $selection,
            executionId: null,
        );

        $reservationId = $this->settledReservation((string) $tenant->id);
        $this->execution(
            correlationId: $expiredExecutionId,
            tenantId: (string) $tenant->id,
            reservationId: $reservationId,
            conversationId: $expired,
            requestMessageId: $expiredUserMessageId,
            responseMessageId: $expiredAssistantMessageId,
            selection: $selection,
            status: 'completed',
            retentionExpiresAt: now()->subHour(),
        );
        DB::table('ai_messages')->where('id', $expiredAssistantMessageId)->update([
            'execution_id' => $expiredExecutionId,
        ]);

        DB::table('dovra_legal_holds')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->id,
            'subject_type' => 'ai_conversation',
            'subject_id' => $held,
            'requested_by' => null,
            'reason' => 'Litigation hold',
            'status' => 'active',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        $report = app(AiConversationRetention::class)->prune(100);

        self::assertSame(1, $report->conversationsPruned);
        self::assertSame(2, $report->messagesRedacted);
        self::assertSame(1, $report->evidenceRedacted);
        self::assertSame(1, $report->providerPayloadsPruned);
        self::assertSame(1, $report->legalHoldsSkipped);
        self::assertSame([], $report->failures);

        self::assertNull(DB::table('ai_messages')->where('id', $expiredUserMessageId)->value('content_ciphertext'));
        self::assertNull(DB::table('ai_messages')->where('id', $expiredAssistantMessageId)->value('content_ciphertext'));
        self::assertNotNull(DB::table('ai_messages')->where('id', $heldUserMessageId)->value('content_ciphertext'));
        self::assertSame(
            hash('sha256', 'expired user content'),
            DB::table('ai_messages')->where('id', $expiredUserMessageId)->value('content_hash'),
        );
        self::assertNull(DB::table('ai_message_evidence')->where('message_id', $expiredAssistantMessageId)->value('excerpt_ciphertext'));
        self::assertSame(
            hash('sha256', 'suspicious retrieved text'),
            DB::table('ai_message_evidence')->where('message_id', $expiredAssistantMessageId)->value('excerpt_hash'),
        );
        self::assertNull(DB::table('ai_executions')->where('correlation_id', $expiredExecutionId)->value('result_ciphertext'));
        self::assertNotNull(DB::table('ai_executions')->where('correlation_id', $expiredExecutionId)->value('payload_pruned_at'));
        self::assertSame(
            'active',
            DB::table('ai_conversations')->where('id', $expired)->value('status'),
        );

        $retry = app(AiConversationRetention::class)->prune(100);

        self::assertSame(0, $retry->conversationsPruned);
        self::assertSame(0, $retry->messagesRedacted);
        self::assertSame(0, $retry->evidenceRedacted);
        self::assertSame(0, $retry->providerPayloadsPruned);
        self::assertSame([], $retry->failures);
        self::assertNotNull(DB::table('ai_messages')->where('conversation_id', $active)->value('content_ciphertext'));
    }

    public function test_prune_command_emits_machine_readable_json(): void
    {
        $tenant = Tenant::factory()->create();
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $conversationId = $this->conversation((string) $tenant->id, retentionExpiresAt: now()->subMinute());
        [$requestMessageId, $responseMessageId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $conversationId,
            selection: $selection,
            executionId: null,
        );
        $executionId = (string) Str::uuid();
        $this->execution(
            correlationId: $executionId,
            tenantId: (string) $tenant->id,
            reservationId: $this->settledReservation((string) $tenant->id),
            conversationId: $conversationId,
            requestMessageId: $requestMessageId,
            responseMessageId: $responseMessageId,
            selection: $selection,
            status: 'completed',
            retentionExpiresAt: now()->subMinute(),
        );
        DB::table('ai_messages')->where('id', $responseMessageId)->update([
            'execution_id' => $executionId,
        ]);

        $exitCode = Artisan::call('dovra:ai-conversations-prune', [
            '--limit' => 1,
            '--json' => true,
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('"conversations_pruned": 1', $output);
        self::assertStringContainsString('"provider_payloads_pruned": 1', $output);
    }

    public function test_prune_command_returns_nonzero_when_retention_reports_blocking_failures(): void
    {
        app()->instance(AiConversationRetention::class, new class extends AiConversationRetention
        {
            public function __construct() {}

            public function prune(int $limit): AiRetentionReport
            {
                return new AiRetentionReport(
                    conversationsPruned: 0,
                    messagesRedacted: 0,
                    evidenceRedacted: 0,
                    providerPayloadsPruned: 0,
                    legalHoldsSkipped: 0,
                    failures: ['lock_timeout'],
                );
            }
        });

        $exitCode = Artisan::call('dovra:ai-conversations-prune', ['--json' => true]);

        self::assertSame(1, $exitCode);
        $output = Artisan::output();
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('failed', $payload['status']);
        self::assertSame(['lock_timeout'], $payload['failures'], $output);
    }

    private function conversation(
        string $tenantId,
        string $status = 'active',
        ?\DateTimeInterface $retentionExpiresAt = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('ai_conversations')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'visibility' => 'tenant_private',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => 'owner-001',
            'agent_version_id' => null,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => $status,
            'retention_category' => 'standard',
            'retention_expires_at' => $retentionExpiresAt,
            'last_message_sequence' => 2,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    /** @return array{string,string} */
    private function messages(
        string $tenantId,
        string $conversationId,
        object $selection,
        ?string $executionId,
    ): array {
        $userMessageId = (string) Str::uuid();
        $assistantMessageId = (string) Str::uuid();

        DB::table('ai_messages')->insert([
            [
                'id' => $userMessageId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 1,
                'direction' => 'inbound',
                'role' => 'user',
                'content_ciphertext' => 'cipher:user',
                'content_hash' => hash('sha256', 'expired user content'),
                'encryption_key_version' => 'v1',
                'content_type' => 'text',
                'idempotency_key' => 'retention-user-'.$userMessageId,
                'idempotency_hash' => hash('sha256', 'retention-user-'.$userMessageId),
                'status' => 'accepted',
                'agent_version_id' => $selection->agentVersionId,
                'prompt_version_id' => $selection->promptVersionId,
                'provider_version_id' => $selection->providerVersionId,
                'model_version_id' => $selection->modelVersionId,
                'execution_id' => null,
                'accepted_at' => now()->utc()->subHour(),
                'completed_at' => null,
                'failed_at' => null,
                'created_at' => now()->utc()->subHour(),
                'updated_at' => now()->utc()->subHour(),
            ],
            [
                'id' => $assistantMessageId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 2,
                'direction' => 'outbound',
                'role' => 'assistant',
                'content_ciphertext' => 'cipher:assistant',
                'content_hash' => hash('sha256', 'assistant content'),
                'encryption_key_version' => 'v1',
                'content_type' => 'json',
                'idempotency_key' => 'retention-assistant-'.$assistantMessageId,
                'idempotency_hash' => hash('sha256', 'retention-assistant-'.$assistantMessageId),
                'status' => 'completed',
                'agent_version_id' => $selection->agentVersionId,
                'prompt_version_id' => $selection->promptVersionId,
                'provider_version_id' => $selection->providerVersionId,
                'model_version_id' => $selection->modelVersionId,
                'execution_id' => $executionId,
                'accepted_at' => now()->utc()->subHour(),
                'completed_at' => now()->utc()->subHour(),
                'failed_at' => null,
                'created_at' => now()->utc()->subHour(),
                'updated_at' => now()->utc()->subHour(),
            ],
        ]);

        DB::table('ai_message_evidence')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'message_id' => $assistantMessageId,
            'evidence_type' => 'resource',
            'resource_type' => 'business',
            'resource_id' => $tenantId,
            'content_hash' => hash('sha256', 'source hash'),
            'metadata' => json_encode(['classification' => 'grounding'], JSON_THROW_ON_ERROR),
            'evidence_id' => 'evidence-'.$assistantMessageId,
            'source_type' => 'knowledge_chunk',
            'source_id' => 'chunk-001',
            'source_version' => hash('sha256', 'v1'),
            'source_tenant_id' => $tenantId,
            'visibility' => 'tenant_private',
            'citation_label' => 'Internal notes',
            'fresh_at' => now()->utc()->subHour(),
            'retrieval_rank' => 1,
            'retrieval_score' => 0.99100000,
            'currency' => 'HNL',
            'price_minor' => 100,
            'availability' => 'available',
            'excerpt_ciphertext' => 'cipher:excerpt',
            'excerpt_hash' => hash('sha256', 'suspicious retrieved text'),
            'excerpt_encryption_key_version' => 'v1',
            'created_at' => now()->utc()->subHour(),
            'updated_at' => now()->utc()->subHour(),
        ]);

        return [$userMessageId, $assistantMessageId];
    }

    private function settledReservation(string $tenantId): string
    {
        $id = (string) Str::uuid();
        DB::table('ai_credit_reservations')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'estimated_cost' => 0.25,
            'actual_cost' => 0.10,
            'denomination' => 'commercial_credits',
            'pricing_version' => 'credits-v1',
            'credits_per_provider_usd' => 1.0,
            'minimum_charge' => 0.01,
            'status' => 'settled',
            'operation' => 'private_agent.answer',
            'failure_reason' => null,
            'settled_at' => now()->utc()->subHour(),
            'refunded_at' => null,
            'created_at' => now()->utc()->subHours(2),
            'updated_at' => now()->utc()->subHour(),
        ]);

        return $id;
    }

    private function execution(
        string $correlationId,
        string $tenantId,
        string $reservationId,
        string $conversationId,
        string $requestMessageId,
        string $responseMessageId,
        object $selection,
        string $status,
        \DateTimeInterface $retentionExpiresAt,
    ): void {
        DB::table('ai_executions')->insert([
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', $correlationId),
            'claim_token' => hash('sha256', 'claim:'.$correlationId),
            'claim_version' => 1,
            'status' => $status,
            'reservation_id' => $reservationId,
            'result_ciphertext' => 'cipher:provider-result',
            'result_hash' => hash('sha256', 'provider-result'),
            'model' => $selection->modelKey,
            'usage' => json_encode([
                'input_tokens' => 10,
                'output_tokens' => 5,
                'cost_usd' => 0.10,
                'charged_cost_usd' => 0.10,
                'provider_cost_usd' => 0.10,
            ], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 125,
            'provider_cost' => 0.10,
            'charged_cost' => 0.10,
            'charged_credits' => 0.10,
            'credit_pricing_version' => 'credits-v1',
            'credits_per_provider_usd' => 1.0,
            'credit_minimum_charge' => 0.01,
            'failure_code' => null,
            'encryption_key_version' => 'v1',
            'retention_expires_at' => $retentionExpiresAt,
            'processing_expires_at' => now()->utc()->subHour(),
            'provider_completed_at' => now()->utc()->subHour(),
            'completed_at' => now()->utc()->subHour(),
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'response_message_id' => $responseMessageId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now()->utc()->subHours(2),
            'updated_at' => now()->utc()->subHour(),
        ]);
    }
}
