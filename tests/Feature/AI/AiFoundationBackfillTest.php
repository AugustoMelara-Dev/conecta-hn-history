<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AiFoundationBackfill;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class AiFoundationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_is_restartable_idempotent_and_only_links_exact_registry_and_conversation_matches(): void
    {
        $tenant = Tenant::factory()->create();
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);

        $linkedConversation = $this->conversation((string) $tenant->id, 'owner-001', null);
        [$linkedRequestId, $linkedResponseId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $linkedConversation,
            selection: $selection,
        );

        $ambiguousLeftConversation = $this->conversation((string) $tenant->id, 'owner-001', null);
        [$ambiguousRequestId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $ambiguousLeftConversation,
            selection: $selection,
        );
        $ambiguousRightConversation = $this->conversation((string) $tenant->id, 'owner-001', null);
        [, $ambiguousResponseId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $ambiguousRightConversation,
            selection: $selection,
        );

        $restartableExecutionId = (string) Str::uuid();
        DB::table('ai_executions')->insert([
            'correlation_id' => $restartableExecutionId,
            'tenant_id' => (string) $tenant->id,
            'billing_tenant_id' => (string) $tenant->id,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'restartable'),
            'claim_token' => hash('sha256', 'restartable-claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'reservation_id' => null,
            'result_ciphertext' => 'cipher:restartable',
            'result_hash' => hash('sha256', 'restartable-result'),
            'model' => $selection->modelKey,
            'usage' => json_encode(['input_tokens' => 5, 'output_tokens' => 4], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 20,
            'provider_cost' => 0.01,
            'charged_cost' => 0.01,
            'failure_code' => null,
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDays(5),
            'processing_expires_at' => now()->subMinute(),
            'provider_completed_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => null,
            'request_message_id' => $linkedRequestId,
            'response_message_id' => $linkedResponseId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $ambiguousExecutionId = (string) Str::uuid();
        DB::table('ai_executions')->insert([
            'correlation_id' => $ambiguousExecutionId,
            'tenant_id' => (string) $tenant->id,
            'billing_tenant_id' => (string) $tenant->id,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'ambiguous'),
            'claim_token' => hash('sha256', 'ambiguous-claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'reservation_id' => null,
            'result_ciphertext' => 'cipher:ambiguous',
            'result_hash' => hash('sha256', 'ambiguous-result'),
            'model' => $selection->modelKey,
            'usage' => json_encode(['input_tokens' => 5, 'output_tokens' => 4], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 25,
            'provider_cost' => 0.01,
            'charged_cost' => 0.01,
            'failure_code' => null,
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDays(5),
            'processing_expires_at' => now()->subMinute(),
            'provider_completed_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => null,
            'request_message_id' => $ambiguousRequestId,
            'response_message_id' => $ambiguousResponseId,
            'provider_version_id' => null,
            'model_version_id' => null,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now()->subMinutes(50),
            'updated_at' => now()->subMinutes(50),
        ]);

        $first = app(AiFoundationBackfill::class)->run(500);
        $second = app(AiFoundationBackfill::class)->run(500);

        self::assertSame($first->registryLinked, $second->registryLinked);
        self::assertSame($first->conversationLinked, $second->conversationLinked);
        self::assertSame($first->retentionClassified, $second->retentionClassified);
        self::assertSame(2, $second->registryLinked);
        self::assertSame(1, $second->conversationLinked);
        self::assertSame(1, $second->legacyUnresolved);
        self::assertSame(3, $second->retentionClassified);
        self::assertSame(0, $second->gaps);
        self::assertSame(1, $second->conflicts);

        $this->assertDatabaseHas('ai_executions', [
            'correlation_id' => $restartableExecutionId,
            'conversation_id' => $linkedConversation,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
        ]);
        $this->assertDatabaseHas('ai_executions', [
            'correlation_id' => $ambiguousExecutionId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
        ]);
        self::assertNull(DB::table('ai_executions')
            ->where('correlation_id', $ambiguousExecutionId)
            ->value('conversation_id'));
        self::assertSame(3, DB::table('ai_conversations')->count());
        self::assertSame(
            1,
            DB::table('ai_conversation_participants')
                ->where('tenant_id', (string) $tenant->id)
                ->where('conversation_id', $linkedConversation)
                ->where('participant_type', 'actor')
                ->where('participant_id', 'owner-001')
                ->count(),
        );
        self::assertSame(
            'conversation_standard',
            DB::table('ai_conversations')->where('id', $linkedConversation)->value('retention_category'),
        );
    }

    public function test_backfill_command_emits_machine_readable_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $conversationId = $this->conversation((string) $tenant->id, 'owner-001', 'conversation_standard');
        [$requestMessageId, $responseMessageId] = $this->messages(
            tenantId: (string) $tenant->id,
            conversationId: $conversationId,
            selection: $selection,
        );

        DB::table('ai_executions')->insert([
            'correlation_id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->id,
            'billing_tenant_id' => (string) $tenant->id,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'command'),
            'claim_token' => hash('sha256', 'command-claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'reservation_id' => null,
            'result_ciphertext' => 'cipher:command',
            'result_hash' => hash('sha256', 'command-result'),
            'model' => $selection->modelKey,
            'usage' => json_encode(['input_tokens' => 1, 'output_tokens' => 1], JSON_THROW_ON_ERROR),
            'latency_milliseconds' => 10,
            'provider_cost' => 0.01,
            'charged_cost' => 0.01,
            'failure_code' => null,
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->subMinute(),
            'provider_completed_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
            'failed_at' => null,
            'payload_pruned_at' => null,
            'conversation_id' => null,
            'request_message_id' => $requestMessageId,
            'response_message_id' => $responseMessageId,
            'provider_version_id' => null,
            'model_version_id' => null,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $exitCode = Artisan::call('dovra:ai-foundation-backfill', [
            '--limit' => 25,
            '--json' => true,
        ]);

        self::assertSame(0, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('"registry_linked": 1', $output);
        self::assertStringContainsString('"conversation_linked": 1', $output);
        self::assertStringContainsString('"legacy_unresolved": 0', $output);
    }

    private function conversation(string $tenantId, string $actorId, ?string $retentionCategory): string
    {
        $id = (string) Str::uuid();

        DB::table('ai_conversations')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'visibility' => 'tenant_private',
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => $actorId,
            'agent_version_id' => null,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => 'active',
            'retention_category' => $retentionCategory ?? '',
            'retention_expires_at' => null,
            'last_message_sequence' => 2,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        return $id;
    }

    /** @return array{string,string} */
    private function messages(string $tenantId, string $conversationId, object $selection): array
    {
        $requestId = (string) Str::uuid();
        $responseId = (string) Str::uuid();

        DB::table('ai_messages')->insert([
            [
                'id' => $requestId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 1,
                'direction' => 'inbound',
                'role' => 'user',
                'content_ciphertext' => 'cipher:request',
                'content_hash' => hash('sha256', $requestId),
                'encryption_key_version' => 'v1',
                'content_type' => 'text',
                'idempotency_key' => 'request-'.$requestId,
                'idempotency_hash' => hash('sha256', 'request-'.$requestId),
                'status' => 'accepted',
                'agent_version_id' => null,
                'prompt_version_id' => null,
                'provider_version_id' => null,
                'model_version_id' => null,
                'execution_id' => null,
                'accepted_at' => now()->subHour(),
                'completed_at' => null,
                'failed_at' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
            [
                'id' => $responseId,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'sequence' => 2,
                'direction' => 'outbound',
                'role' => 'assistant',
                'content_ciphertext' => 'cipher:response',
                'content_hash' => hash('sha256', $responseId),
                'encryption_key_version' => 'v1',
                'content_type' => 'application/json',
                'idempotency_key' => 'response-'.$responseId,
                'idempotency_hash' => hash('sha256', 'response-'.$responseId),
                'status' => 'completed',
                'agent_version_id' => $selection->agentVersionId,
                'prompt_version_id' => $selection->promptVersionId,
                'provider_version_id' => $selection->providerVersionId,
                'model_version_id' => $selection->modelVersionId,
                'execution_id' => null,
                'accepted_at' => now()->subHour(),
                'completed_at' => now()->subHour(),
                'failed_at' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
        ]);

        return [$requestId, $responseId];
    }
}
