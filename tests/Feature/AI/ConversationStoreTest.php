<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Conversation\AcceptConversationMessage;
use App\Application\Ai\Conversation\ConversationMessage;
use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class ConversationStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_claim_opens_a_private_conversation_and_reuses_an_external_thread(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);

        $first = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'web-private-001',
            externalThreadReference: 'browser-thread-sensitive-001',
        ));
        $second = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'web-private-002',
            externalThreadReference: 'browser-thread-sensitive-001',
            content: 'Now compare that with yesterday.',
        ));
        $standalone = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'web-private-003',
            content: 'Start a separate conversation.',
        ));

        self::assertFalse($first->replayed);
        self::assertSame(1, $first->requestMessage->sequence);
        self::assertSame($first->conversationId, $second->conversationId);
        self::assertSame(2, $second->requestMessage->sequence);
        self::assertNotSame($first->conversationId, $standalone->conversationId);
        self::assertSame(1, $standalone->requestMessage->sequence);
        self::assertSame(2, DB::table('ai_conversations')->count());
        self::assertSame(
            hash('sha256', 'browser-thread-sensitive-001'),
            DB::table('ai_conversations')->where('id', $first->conversationId)->value('external_thread_hash'),
        );
        self::assertNotSame(
            'What did I sell today?',
            DB::table('ai_messages')->where('id', $first->requestMessage->id)->value('content_ciphertext'),
        );
    }

    public function test_public_conversation_is_owned_only_by_the_configured_platform_tenant(): void
    {
        $platform = Tenant::factory()->create();
        $merchant = Tenant::factory()->create();
        $this->supportMarket();
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        $this->scope((string) $platform->id);
        $store = app(ConversationStore::class);

        $claim = $store->claim($this->command(
            (string) $platform->id,
            visibility: 'platform_public',
            actorId: null,
            idempotencyKey: 'public-001',
        ));

        self::assertSame(
            (string) $platform->id,
            DB::table('ai_conversations')->where('id', $claim->conversationId)->value('tenant_id'),
        );

        app(TenantContext::class)->reset();
        $this->scope((string) $merchant->id);

        try {
            $store->claim($this->command(
                (string) $merchant->id,
                visibility: 'platform_public',
                actorId: null,
                idempotencyKey: 'public-merchant-result-001',
            ));
            self::fail('A merchant result tenant must not become public conversation owner.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('ai_access_denied', $exception->reason);
        }

        self::assertSame(1, DB::table('ai_conversations')->count());
    }

    public function test_same_normalized_claim_replays_and_changed_envelope_conflicts_globally(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $command = $this->command(
            (string) $tenant->id,
            idempotencyKey: 'global-idempotency-001',
            content: "Line one.\r\nLine two.",
        );

        $first = $store->claim($command);
        $replay = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'global-idempotency-001',
            content: "Line one.\nLine two.",
        ));

        self::assertTrue($replay->replayed);
        self::assertSame($first->conversationId, $replay->conversationId);
        self::assertSame($first->requestMessage->id, $replay->requestMessage->id);
        self::assertNull($replay->response);
        self::assertSame(1, DB::table('ai_messages')->count());

        foreach ([
            ['content' => 'Different meaning.'],
            ['originType' => 'api'],
            ['externalThreadReference' => 'different-thread'],
            ['currency' => 'HNL'],
        ] as $change) {
            try {
                $store->claim($this->command(
                    (string) $tenant->id,
                    idempotencyKey: 'global-idempotency-001',
                    content: $change['content'] ?? "Line one.\nLine two.",
                    originType: $change['originType'] ?? 'web',
                    externalThreadReference: $change['externalThreadReference'] ?? null,
                    currency: $change['currency'] ?? 'CAD',
                ));
                self::fail('A changed canonical envelope must conflict.');
            } catch (AiConversationConflictException $exception) {
                self::assertSame('ai_request_conflict', $exception->publicCode);
                self::assertSame('AI conversation request conflicts with an existing request.', $exception->getMessage());
            }
        }

        self::assertSame(1, DB::table('ai_messages')->count());
    }

    public function test_closed_conversation_and_missing_or_mismatched_tenant_context_fail_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'closed-thread-001',
            externalThreadReference: 'closed-thread',
        ));
        DB::table('ai_conversations')->where('id', $claim->conversationId)->update(['status' => 'closed']);

        $this->assertAccessDenied(fn () => $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'closed-thread-002',
            externalThreadReference: 'closed-thread',
        )));

        app(TenantContext::class)->reset();
        $this->assertAccessDenied(fn () => $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'missing-context-001',
        )));

        $this->scope((string) $other->id);
        $this->assertAccessDenied(fn () => $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'mismatched-context-001',
        )));
    }

    public function test_envelope_rejects_unsupported_locale_currency_timezone_visibility_and_content_type(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);

        foreach ([
            ['locale' => 'fr-CA'],
            ['currency' => 'USD'],
            ['timezone' => 'America/Vancouver'],
            ['marketCountryCode' => 'US'],
            ['visibility' => 'private'],
            ['contentType' => 'text/html'],
        ] as $index => $change) {
            try {
                $store->claim($this->command(
                    (string) $tenant->id,
                    idempotencyKey: "invalid-envelope-{$index}",
                    locale: $change['locale'] ?? 'en-CA',
                    currency: $change['currency'] ?? 'CAD',
                    timezone: $change['timezone'] ?? 'America/Toronto',
                    marketCountryCode: $change['marketCountryCode'] ?? 'CA',
                    visibility: $change['visibility'] ?? 'tenant_private',
                    contentType: $change['contentType'] ?? 'text',
                ));
                self::fail('Unsupported conversation envelope values must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('AI conversation envelope is invalid.', $exception->getMessage());
            }
        }

        self::assertSame(0, DB::table('ai_conversations')->count());
        self::assertSame(0, DB::table('ai_messages')->count());
    }

    public function test_completion_replays_one_assistant_message_with_exact_lineage_and_validated_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'complete-001',
        ));
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $result = $this->executionResult(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
        );
        $answer = ['answer' => 'You sold 12 products.', 'confidence' => 'grounded'];
        $evidence = [[
            'evidence_type' => 'resource',
            'resource_type' => 'business',
            'resource_id' => (string) $tenant->id,
            'source_tenant_id' => (string) $tenant->id,
            'content_hash' => hash('sha256', 'tenant-owned-grounded-fact'),
        ]];

        $response = $store->completeAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
            $result,
            $answer,
            $evidence,
        );
        $again = $store->completeAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
            $result,
            $answer,
            $evidence,
        );
        $replay = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'complete-001',
        ));

        self::assertSame($response->id, $again->id);
        self::assertSame($response->id, $replay->response?->id);
        self::assertInstanceOf(ConversationMessage::class, $replay->response);
        self::assertTrue($replay->replayed);
        self::assertSame(2, $response->sequence);
        self::assertSame($answer, $response->content);
        self::assertSame(1, DB::table('ai_messages')->where('role', 'assistant')->count());
        self::assertSame(1, DB::table('ai_message_evidence')->count());

        $stored = DB::table('ai_messages')->where('id', $response->id)->first();
        self::assertNotNull($stored);
        self::assertSame($selection->agentVersionId, $stored->agent_version_id);
        self::assertSame($selection->promptVersionId, $stored->prompt_version_id);
        self::assertSame($selection->providerVersionId, $stored->provider_version_id);
        self::assertSame($selection->modelVersionId, $stored->model_version_id);
        self::assertSame($result->correlationId, $stored->execution_id);
        self::assertSame($response->id, DB::table('ai_executions')
            ->where('correlation_id', $result->correlationId)->value('response_message_id'));
        foreach (['ai_conversations', 'ai_messages', 'ai_message_evidence', 'ai_message_failures'] as $table) {
            $serialized = json_encode(
                DB::table($table)->get()->map(static fn (object $row): array => (array) $row)->all(),
                JSON_THROW_ON_ERROR,
            );
            self::assertStringNotContainsString('You sold 12 products.', $serialized, $table);
            self::assertStringNotContainsString('tenant-owned-grounded-fact', $serialized, $table);
        }
    }

    public function test_completion_rejects_cross_tenant_or_unscoped_evidence_before_persisting(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'evidence-invalid-001',
        ));
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $result = $this->executionResult(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
        );

        foreach ([
            [[
                'evidence_type' => 'resource',
                'resource_type' => 'business',
                'resource_id' => (string) $other->id,
                'source_tenant_id' => (string) $other->id,
            ]],
            [[
                'evidence_type' => 'resource',
                'resource_type' => 'business',
                'resource_id' => (string) $tenant->id,
                'source_tenant_id' => (string) $tenant->id,
                'excerpt' => 'plaintext must never enter evidence metadata',
            ]],
            [[
                'evidence_type' => 'resource',
                'resource_type' => 'arbitrary_table',
                'resource_id' => (string) $tenant->id,
                'source_tenant_id' => (string) $tenant->id,
            ]],
        ] as $evidence) {
            try {
                $store->completeAssistantMessage(
                    (string) $tenant->id,
                    $claim->conversationId,
                    $claim->requestMessage->id,
                    $selection,
                    $result,
                    ['answer' => 'Safe answer.'],
                    $evidence,
                );
                self::fail('Unowned or unvalidated evidence must be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('AI conversation evidence is invalid.', $exception->getMessage());
            }
        }

        self::assertSame(0, DB::table('ai_messages')->where('role', 'assistant')->count());
        self::assertSame(0, DB::table('ai_message_evidence')->count());
        self::assertSame(1, DB::table('ai_conversations')->value('last_message_sequence'));
    }

    public function test_completion_rejects_nonterminal_execution_lineage(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'nonterminal-execution-001',
        ));
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $result = $this->executionResult(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
        );
        DB::table('ai_executions')->where('correlation_id', $result->correlationId)->update([
            'status' => 'processing',
            'completed_at' => null,
        ]);

        $this->assertAccessDenied(fn () => $store->completeAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            $selection,
            $result,
            ['answer' => 'Must not persist before accounting completes.'],
            [],
        ));

        self::assertSame(0, DB::table('ai_messages')->where('role', 'assistant')->count());
        self::assertSame(1, DB::table('ai_conversations')->value('last_message_sequence'));
    }

    public function test_failure_is_idempotent_keeps_the_request_and_stores_one_allowlisted_safe_record(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $claim = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'failure-001',
            content: 'Durable sensitive request.',
        ));

        $store->failAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            'ai_temporarily_unavailable',
            true,
        );
        $store->failAssistantMessage(
            (string) $tenant->id,
            $claim->conversationId,
            $claim->requestMessage->id,
            'ai_temporarily_unavailable',
            true,
        );
        $replay = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'failure-001',
            content: 'Durable sensitive request.',
        ));

        self::assertSame($claim->requestMessage->id, $replay->requestMessage->id);
        self::assertSame('failed', $replay->response?->status);
        self::assertSame(1, DB::table('ai_messages')->where('role', 'user')->count());
        self::assertSame(1, DB::table('ai_messages')->where('role', 'assistant')->count());
        self::assertSame(1, DB::table('ai_message_failures')->count());
        self::assertSame('ai_temporarily_unavailable', DB::table('ai_message_failures')->value('failure_code'));
        self::assertNull(DB::table('ai_message_failures')->value('failure_detail'));
        self::assertSame(2, DB::table('ai_conversations')->value('last_message_sequence'));

        try {
            $store->failAssistantMessage(
                (string) $tenant->id,
                $claim->conversationId,
                $claim->requestMessage->id,
                'provider stack trace: secret',
                false,
            );
            self::fail('Provider exception text must not be accepted as a public failure code.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('AI conversation failure code is invalid.', $exception->getMessage());
        }
    }

    public function test_completion_and_failure_are_tenant_conversation_and_request_bound(): void
    {
        $tenant = Tenant::factory()->create();
        $this->supportMarket();
        $this->scope((string) $tenant->id);
        $store = app(ConversationStore::class);
        $first = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'bound-001',
        ));
        $second = $store->claim($this->command(
            (string) $tenant->id,
            idempotencyKey: 'bound-002',
            content: 'Separate conversation.',
        ));
        $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
        $result = $this->executionResult(
            (string) $tenant->id,
            $first->conversationId,
            $first->requestMessage->id,
            $selection,
        );

        $this->assertAccessDenied(fn () => $store->completeAssistantMessage(
            (string) $tenant->id,
            $second->conversationId,
            $first->requestMessage->id,
            $selection,
            $result,
            ['answer' => 'Must fail.'],
            [],
        ));
        $this->assertAccessDenied(fn () => $store->failAssistantMessage(
            (string) $tenant->id,
            $second->conversationId,
            $first->requestMessage->id,
            'ai_handoff_required',
            false,
        ));

        self::assertSame(0, DB::table('ai_messages')->where('role', 'assistant')->count());
    }

    private function command(
        string $tenantId,
        string $visibility = 'tenant_private',
        string $originType = 'web',
        ?string $externalThreadReference = null,
        ?string $actorId = 'owner-001',
        string $locale = 'en-CA',
        string $marketCountryCode = 'CA',
        string $currency = 'CAD',
        string $timezone = 'America/Toronto',
        string $content = 'What did I sell today?',
        string $contentType = 'text',
        string $idempotencyKey = 'web-private-001',
    ): AcceptConversationMessage {
        return new AcceptConversationMessage(
            tenantId: $tenantId,
            visibility: $visibility,
            originType: $originType,
            externalThreadReference: $externalThreadReference,
            actorId: $actorId,
            locale: $locale,
            marketCountryCode: $marketCountryCode,
            currency: $currency,
            timezone: $timezone,
            content: $content,
            contentType: $contentType,
            idempotencyKey: $idempotencyKey,
        );
    }

    private function supportMarket(): void
    {
        $now = now();
        DB::table('countries')->insert([
            'code' => 'CA', 'name' => 'Canada', 'native_name' => 'Canada',
            'calling_code' => '+1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            ['code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$', 'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'HNL', 'name' => 'Lempira', 'symbol' => 'L', 'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA', 'name' => 'English (Canada)', 'native_name' => 'English (Canada)',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto', 'label' => 'Toronto', 'utc_offset' => '-05:00',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function scope(string $tenantId): void
    {
        app(TenantContext::class)->setTenant($tenantId);
    }

    private function assertAccessDenied(callable $operation): void
    {
        try {
            $operation();
            self::fail('The operation must fail closed.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('ai_access_denied', $exception->reason);
            self::assertSame('AI execution denied.', $exception->getMessage());
        }
    }

    private function executionResult(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        object $selection,
    ): AiExecutionResult {
        $correlationId = (string) Str::uuid();
        DB::table('ai_executions')->insert([
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'billing_tenant_id' => $tenantId,
            'actor_id' => 'owner-001',
            'audience' => 'private',
            'capability' => 'private_tenant_agent',
            'operation' => 'private_agent.answer',
            'provider' => $selection->providerKey,
            'request_hash' => hash('sha256', 'request'),
            'claim_token' => hash('sha256', 'claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->addMinute(),
            'provider_completed_at' => now(),
            'completed_at' => now(),
            'conversation_id' => $conversationId,
            'request_message_id' => $requestMessageId,
            'provider_version_id' => $selection->providerVersionId,
            'model_version_id' => $selection->modelVersionId,
            'agent_version_id' => $selection->agentVersionId,
            'prompt_version_id' => $selection->promptVersionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiExecutionResult(
            correlationId: $correlationId,
            provider: $selection->providerKey,
            model: $selection->modelKey,
            data: ['answer' => 'You sold 12 products.'],
            usage: [
                'input_tokens' => 10,
                'output_tokens' => 8,
                'cost_usd' => 0.01,
                'charged_cost_usd' => 0.01,
                'provider_cost_usd' => 0.01,
            ],
            latencyMilliseconds: 42,
        );
    }
}
