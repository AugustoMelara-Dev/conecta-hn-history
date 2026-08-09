<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Conversation\ConversationRuntime;
use App\Application\Ai\Conversation\PrivateConversationInput;
use App\Application\Ai\Conversation\PublicConversationInput;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\City;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ConversationRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_runtime_accepts_the_server_owned_conversation_envelope(): void
    {
        [$tenant, $product, $input, $gateway] = $this->privateCatalogFixture();
        app(AiRegistryResolver::class)->resolve($input->toRegistryQuery(
            app(TenantMarketResolver::class)->resolve($tenant->fresh()),
        ));
        $response = app(ConversationRuntime::class)->private($input);

        self::assertSame('completed', $response->status, json_encode($response->toArray(), JSON_THROW_ON_ERROR));
        self::assertNotNull($response->conversationId);
        self::assertNotNull($gateway->agentVersionAtExecution);
        self::assertSame(
            DB::table('ai_conversations')->where('id', $response->conversationId)->value('agent_version_id'),
            $gateway->agentVersionAtExecution,
        );
        $this->assertDatabaseHas('ai_messages', [
            'id' => $response->responseMessageId,
            'conversation_id' => $response->conversationId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('ai_executions', [
            'correlation_id' => $response->requestMessageId,
            'conversation_id' => $response->conversationId,
            'request_message_id' => $response->requestMessageId,
        ]);
        self::assertSame(1, $gateway->calls);
        self::assertSame(['tenant_catalog.read'], $gateway->allowedToolsAtExecution);
        self::assertSame((string) $tenant->id, $gateway->executionTenantId);
        self::assertSame('manager', json_decode(
            (string) DB::table('ai_agent_versions')
                ->where('id', $gateway->agentVersionAtExecution)
                ->value('configuration'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['workforce_role'] ?? null);
    }

    public function test_roleless_private_registry_version_fails_closed_before_provider_and_replays_safely(): void
    {
        [, , $input, $gateway] = $this->privateCatalogFixture(workforceRole: null);

        $first = app(ConversationRuntime::class)->private($input);
        $replay = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $first->status);
        self::assertSame('ai_configuration_unavailable', $first->code);
        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame(0, $gateway->calls);
        self::assertSame([], $gateway->allowedToolsAtExecution);
        self::assertSame(0, DB::table('ai_executions')->count());
        self::assertSame(0, DB::table('ai_handoffs')->count());
        self::assertSame(1, DB::table('ai_message_failures')
            ->where('failure_code', 'ai_configuration_unavailable')
            ->count());
    }

    public function test_eligible_fixture_rejects_unknown_or_non_private_workforce_roles(): void
    {
        $unknownCounts = $this->registryFixtureCounts();
        try {
            AiEligibleRegistryFixture::create(
                tenantId: (string) Tenant::factory()->create()->getKey(),
                capability: 'private_tenant_agent',
                audience: 'private',
                outputSchema: 'private_agent.evidence_selection.v1',
                workforceRole: 'administrator',
            );
            self::fail('An unknown Workforce role must be rejected by the fixture.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Eligible AI fixture Workforce role is invalid.', $exception->getMessage());
        }
        self::assertSame($unknownCounts, $this->registryFixtureCounts());

        $publicCounts = $this->registryFixtureCounts();
        try {
            AiEligibleRegistryFixture::create(
                tenantId: (string) Tenant::factory()->create()->getKey(),
                capability: 'public_discovery',
                audience: 'public',
                outputSchema: 'public_discovery.answer.v1',
                workforceRole: 'manager',
            );
            self::fail('A public fixture must not receive a Workforce role.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Workforce role is only valid for the private tenant agent fixture.', $exception->getMessage());
        }
        self::assertSame($publicCounts, $this->registryFixtureCounts());
    }

    public function test_replay_returns_the_persisted_response_without_reexecuting_ai_and_continues_by_conversation_id(): void
    {
        [, , $input, $gateway] = $this->privateCatalogFixture();

        $first = app(ConversationRuntime::class)->private($input);
        $replay = app(ConversationRuntime::class)->private($input);
        $continued = app(ConversationRuntime::class)->private(new PrivateConversationInput(
            tenantId: $input->tenantId,
            actorId: $input->actorId,
            message: 'Mostrame disponibilidad de productos',
            idempotencyKey: 'private-runtime-2',
            channel: 'web',
            conversationId: $first->conversationId,
        ));

        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame($first->conversationId, $continued->conversationId);
        self::assertNotSame($first->requestMessageId, $continued->requestMessageId);
        self::assertSame(2, $gateway->calls);
        self::assertSame(4, DB::table('ai_conversations')
            ->where('id', $first->conversationId)
            ->value('last_message_sequence'));
    }

    public function test_conflicting_reuse_and_unowned_conversation_ids_fail_closed(): void
    {
        [, , $input] = $this->privateCatalogFixture();
        $first = app(ConversationRuntime::class)->private($input);

        try {
            app(ConversationRuntime::class)->private(new PrivateConversationInput(
                tenantId: $input->tenantId,
                actorId: $input->actorId,
                message: 'Changed payload',
                idempotencyKey: $input->idempotencyKey,
            ));
            self::fail('A changed payload must conflict.');
        } catch (AiConversationConflictException $exception) {
            self::assertSame('ai_request_conflict', $exception->publicCode);
        }

        try {
            app(ConversationRuntime::class)->private(new PrivateConversationInput(
                tenantId: $input->tenantId,
                actorId: 'different-owner',
                message: 'Show my catalog',
                idempotencyKey: 'private-runtime-other-actor',
                conversationId: $first->conversationId,
            ));
            self::fail('A different actor must not continue the conversation.');
        } catch (AiConversationConflictException) {
            self::assertTrue(true);
        }

        $otherTenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($otherTenant);
        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $otherTenant->id);
        try {
            app(ConversationRuntime::class)->private(new PrivateConversationInput(
                tenantId: (string) $otherTenant->id,
                actorId: $input->actorId,
                message: 'Show my catalog',
                idempotencyKey: 'private-runtime-cross-tenant',
                conversationId: $first->conversationId,
            ));
            self::fail('Another tenant must not learn or continue the conversation.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('ai_access_denied', $exception->reason);
        }

        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant($input->tenantId);
        config()->set('ai.public.billing_tenant_id', $input->tenantId);
        try {
            app(ConversationRuntime::class)->public(new PublicConversationInput(
                message: 'Show published products',
                idempotencyKey: 'public-runtime-visibility-mismatch',
                conversationId: $first->conversationId,
                rateLimitIdentity: '127.0.0.1',
                actorId: $input->actorId,
            ));
            self::fail('A private conversation must not be reopened as public.');
        } catch (AiConversationConflictException) {
            self::assertTrue(true);
        }

        $this->expectException(AiExecutionDeniedException::class);
        app(ConversationRuntime::class)->private(new PrivateConversationInput(
            tenantId: $input->tenantId,
            actorId: $input->actorId,
            message: 'Show my catalog',
            idempotencyKey: 'private-runtime-missing',
            conversationId: (string) Str::uuid(),
        ));
    }

    public function test_local_fallback_is_non_billable_persisted_and_replayable(): void
    {
        [$tenant] = $this->privateCatalogFixture();
        $gateway = new PersistingConversationGateway(['evidence' => []]);
        $this->app->instance(AiGateway::class, $gateway);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Help me',
            idempotencyKey: 'private-runtime-local-fallback',
        );

        $first = app(ConversationRuntime::class)->private($input);
        $replay = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $first->status);
        self::assertSame('ai_evidence_insufficient', $first->code);
        self::assertTrue((bool) $first->payload['fallback']);
        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame(0, $gateway->calls);
        self::assertSame(0, DB::table('ai_executions')->count());
        self::assertNotNull(DB::table('ai_messages')
            ->where('id', $first->responseMessageId)
            ->value('content_ciphertext'));
    }

    public function test_provider_outage_returns_only_a_safe_code_and_preserves_non_ai_state(): void
    {
        [$tenant, $product] = $this->privateCatalogFixture();
        $this->app->instance(AiGateway::class, new ThrowingConversationGateway);
        $productCount = Product::query()->count();
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog',
            idempotencyKey: 'private-runtime-outage',
        );

        $response = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $response->status);
        self::assertSame('ai_temporarily_unavailable', $response->code);
        self::assertStringNotContainsString('SECRET PROVIDER DETAIL', json_encode(
            $response->toArray(),
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame($productCount, Product::query()->count());
        self::assertTrue(Product::query()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('ai_message_failures', [
            'message_id' => $response->responseMessageId,
            'failure_code' => 'ai_temporarily_unavailable',
            'failure_detail' => null,
        ]);
        self::assertSame('queued', $response->handoff['status'] ?? null);
        self::assertSame('A human follow-up has been requested.', $response->handoff['message'] ?? null);
        self::assertSame($response->handoff, app(ConversationRuntime::class)->private($input)->handoff);
        self::assertSame(1, DB::table('ai_handoffs')->count());
    }

    public function test_explicit_human_request_is_bounded_persisted_and_replayed_without_calling_provider(): void
    {
        [$tenant, , , $gateway] = $this->privateCatalogFixture();
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Necesito hablar con una persona, por favor.',
            idempotencyKey: 'private-runtime-explicit-handoff',
        );

        $first = app(ConversationRuntime::class)->private($input);
        $replay = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $first->status);
        self::assertSame('ai_handoff_required', $first->code);
        self::assertSame('queued', $first->handoff['status'] ?? null);
        self::assertSame('A human follow-up has been requested.', $first->handoff['message'] ?? null);
        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame(0, $gateway->calls);
        self::assertSame(1, DB::table('ai_handoffs')->count());
    }

    public function test_active_handoff_ownership_blocks_ai_and_persists_one_replayable_safe_response(): void
    {
        [$tenant, , , $gateway] = $this->privateCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $opened = $runtime->private(new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'I need to speak with a human, please.',
            idempotencyKey: 'private-runtime-active-handoff-open',
        ));
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog now.',
            idempotencyKey: 'private-runtime-active-handoff-block',
            conversationId: $opened->conversationId,
        );

        $blocked = $runtime->private($input);
        $replay = $runtime->private($input);

        self::assertSame('failed', $blocked->status);
        self::assertSame('ai_handoff_required', $blocked->code);
        self::assertSame($opened->handoff['id'], $blocked->handoff['id'] ?? null);
        self::assertSame('queued', $blocked->handoff['status'] ?? null);
        self::assertSame('A human follow-up has been requested.', $blocked->handoff['message'] ?? null);
        self::assertSame($blocked->toArray(), $replay->toArray());
        self::assertSame(0, $gateway->calls);
        self::assertSame(1, DB::table('ai_handoffs')->count());
        self::assertSame(2, DB::table('outbox_messages')->where('aggregate_id', $opened->handoff['id'])->count());
    }

    public function test_resolved_handoff_without_safe_return_keeps_ai_blocked_and_replayable(): void
    {
        [$tenant, , , $gateway] = $this->privateCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $opened = $runtime->private(new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'I need to speak with a human, please.',
            idempotencyKey: 'private-runtime-resolved-block-open',
        ));
        DB::table('ai_handoffs')->where('id', $opened->handoff['id'])->update([
            'status' => 'resolved',
            'return_to_agent' => false,
            'active_deduplication_key' => null,
            'resolved_at' => now(),
            'completed_at' => now(),
        ]);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Can the AI continue?',
            idempotencyKey: 'private-runtime-resolved-block',
            conversationId: $opened->conversationId,
        );

        $blocked = $runtime->private($input);
        $replay = $runtime->private($input);

        self::assertSame('failed', $blocked->status);
        self::assertSame($opened->handoff['id'], $blocked->handoff['id'] ?? null);
        self::assertSame('resolved', $blocked->handoff['status'] ?? null);
        self::assertSame($blocked->toArray(), $replay->toArray());
        self::assertSame(0, $gateway->calls);
        self::assertSame(2, DB::table('outbox_messages')->where('aggregate_id', $opened->handoff['id'])->count());
    }

    public function test_resolved_handoff_with_safe_return_allows_ai_once_and_replays_completion(): void
    {
        [$tenant, , , $gateway] = $this->privateCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $opened = $runtime->private(new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'I need to speak with a human, please.',
            idempotencyKey: 'private-runtime-return-open',
        ));
        DB::table('ai_handoffs')->where('id', $opened->handoff['id'])->update([
            'status' => 'resolved',
            'return_to_agent' => true,
            'active_deduplication_key' => null,
            'resolved_at' => now(),
            'completed_at' => now(),
        ]);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog again.',
            idempotencyKey: 'private-runtime-return-allowed',
            conversationId: $opened->conversationId,
        );

        $completed = $runtime->private($input);
        $replay = $runtime->private($input);

        self::assertSame('completed', $completed->status);
        self::assertNull($completed->handoff);
        self::assertSame($completed->toArray(), $replay->toArray());
        self::assertSame(1, $gateway->calls);
        self::assertSame(2, DB::table('outbox_messages')->where('aggregate_id', $opened->handoff['id'])->count());
    }

    public function test_policy_denial_maps_honestly_to_one_queued_handoff(): void
    {
        [$tenant] = $this->privateCatalogFixture();
        $this->app->instance(AiGateway::class, new PolicyDeniedConversationGateway);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog',
            idempotencyKey: 'private-runtime-policy-handoff',
        );

        $response = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $response->status);
        self::assertSame('queued', $response->handoff['status'] ?? null);
        self::assertSame('policy', DB::table('ai_handoffs')->value('trigger_type'));
        self::assertSame('urgent', DB::table('ai_handoffs')->value('priority'));
    }

    public function test_human_word_without_request_intent_does_not_false_positive(): void
    {
        [$tenant, , , $gateway] = $this->privateCatalogFixture();
        $response = app(ConversationRuntime::class)->private(new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my human resources catalog.',
            idempotencyKey: 'private-runtime-no-explicit-handoff',
        ));

        self::assertSame('completed', $response->status);
        self::assertNull($response->handoff);
        self::assertSame(1, $gateway->calls);
        self::assertSame(0, DB::table('ai_handoffs')->count());
    }

    public function test_public_runtime_uses_only_the_configured_platform_owner(): void
    {
        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        $merchant = Tenant::factory()->create(['is_active' => true]);
        GlobalMarketFixture::assignCanada($merchant);
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Public runtime product',
            'price' => 25,
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        app(TenantContext::class)->setTenant((string) $platform->id);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $platform->id,
            capability: 'public_discovery',
            audience: 'public',
            outputSchema: 'public_discovery.answer.v1',
            marketCountryCode: 'CA',
        );
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            $selection->adapterKey => [
                $selection->secretReference => new FakeAiProvider,
            ],
        ]));
        $gateway = new PersistingConversationGateway([
            'answer' => 'Use exact evidence.',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.$product->id,
                'reason' => 'Published product.',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);

        $response = app(ConversationRuntime::class)->public(new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-runtime-1',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '127.0.0.1',
        ));

        self::assertSame('completed', $response->status, json_encode(
            $response->toArray(),
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame((string) $platform->id, DB::table('ai_conversations')
            ->where('id', $response->conversationId)
            ->value('tenant_id'));
        self::assertNotSame((string) $merchant->id, DB::table('ai_conversations')
            ->where('id', $response->conversationId)
            ->value('tenant_id'));
        self::assertSame(1, $gateway->calls);
    }

    public function test_public_idempotency_conflicts_when_any_response_filter_changes(): void
    {
        $this->publicCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $original = new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-filter-conflict',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: 'anonymous-filter-subject',
        );

        $runtime->public($original);

        foreach ([
            ['budget' => 51.25],
            ['city' => 'tegucigalpa'],
            ['category' => 'cafes'],
        ] as $changedFilter) {
            try {
                $runtime->public(new PublicConversationInput(
                    message: $original->message,
                    idempotencyKey: $original->idempotencyKey,
                    actorId: $original->actorId,
                    budget: $changedFilter['budget'] ?? $original->budget,
                    city: $changedFilter['city'] ?? $original->city,
                    category: $changedFilter['category'] ?? $original->category,
                    rateLimitIdentity: $original->rateLimitIdentity,
                ));
                self::fail('Changing a response-affecting public filter must conflict.');
            } catch (AiConversationConflictException $exception) {
                self::assertSame('ai_request_conflict', $exception->publicCode);
            }
        }
    }

    public function test_public_pseudonymous_subject_separates_conversations_with_identical_payload_and_rate_identity(): void
    {
        [, , $gateway] = $this->publicCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $firstInput = new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-subject-key',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        );

        $first = $runtime->public($firstInput);
        $replay = $runtime->public($firstInput);
        $secondSubject = $runtime->public(new PublicConversationInput(
            message: $firstInput->message,
            idempotencyKey: $firstInput->idempotencyKey,
            actorId: str_repeat('b', 64),
            budget: $firstInput->budget,
            city: $firstInput->city,
            category: $firstInput->category,
            rateLimitIdentity: $firstInput->rateLimitIdentity,
        ));

        self::assertSame($first->toArray(), $replay->toArray());
        self::assertNotSame($first->conversationId, $secondSubject->conversationId);
        self::assertNotSame($first->requestMessageId, $secondSubject->requestMessageId);
        self::assertSame(2, $gateway->calls);
        $storedConversations = json_encode(
            DB::table('ai_conversations')->orderBy('id')->get()->toArray(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString('198.51.100.10', $storedConversations);
        self::assertStringNotContainsString('203.0.113.20', $storedConversations);
    }

    public function test_public_continuation_rejects_a_different_anonymous_subject(): void
    {
        $this->publicCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $first = $runtime->public(new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-subject-first',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        ));

        $continued = $runtime->public(new PublicConversationInput(
            message: 'mostrame mas opciones con 50 lempiras',
            idempotencyKey: 'public-subject-continuation',
            actorId: str_repeat('a', 64),
            conversationId: $first->conversationId,
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        ));

        self::assertSame($first->conversationId, $continued->conversationId);

        $this->expectException(AiConversationConflictException::class);
        $runtime->public(new PublicConversationInput(
            message: 'intento de continuar con 50 lempiras',
            idempotencyKey: 'public-subject-attacker',
            actorId: str_repeat('b', 64),
            conversationId: $first->conversationId,
            budget: 50,
            rateLimitIdentity: '203.0.113.20',
        ));
    }

    public function test_public_pseudonymous_subject_does_not_merge_distinct_idempotency_keys(): void
    {
        $this->publicCatalogFixture();
        $runtime = app(ConversationRuntime::class);
        $first = $runtime->public(new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-subject-key-one',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        ));
        $second = $runtime->public(new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-subject-key-two',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        ));

        self::assertNotSame($first->conversationId, $second->conversationId);
        self::assertNotSame($first->requestMessageId, $second->requestMessageId);
    }

    public function test_unknown_runtime_bug_rethrows_without_persisting_fallback_and_clears_public_context(): void
    {
        $this->publicCatalogFixture();
        $this->app->instance(AiGateway::class, new BuggingConversationGateway);
        $runtime = app(ConversationRuntime::class);
        self::assertFalse(app(TenantContext::class)->isInitialized());

        $caught = null;
        try {
            $runtime->public(new PublicConversationInput(
                message: 'que puedo comprar con 50 lempiras',
                idempotencyKey: 'public-runtime-bug',
                actorId: str_repeat('a', 64),
                budget: 50,
                rateLimitIdentity: '198.51.100.10',
            ));
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $caught);
        self::assertSame('SECRET PROGRAMMING BUG', $caught->getMessage());
        self::assertFalse(app(TenantContext::class)->isInitialized());
        self::assertSame(0, DB::table('ai_message_failures')->count());
        self::assertSame(0, DB::table('ai_messages')->where('direction', 'outbound')->count());
    }

    public function test_public_runtime_clears_context_after_success(): void
    {
        $this->publicCatalogFixture();
        self::assertFalse(app(TenantContext::class)->isInitialized());

        $response = app(ConversationRuntime::class)->public(new PublicConversationInput(
            message: 'que puedo comprar con 50 lempiras',
            idempotencyKey: 'public-context-success',
            actorId: str_repeat('a', 64),
            budget: 50,
            rateLimitIdentity: '198.51.100.10',
        ));

        self::assertSame('completed', $response->status);
        self::assertFalse(app(TenantContext::class)->isInitialized());
    }

    public function test_grounding_rejection_persists_failed_fallback_and_replays_without_second_engine(): void
    {
        [$tenant] = $this->privateCatalogFixture();
        $gateway = new PersistingConversationGateway([
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.Str::uuid(),
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog',
            idempotencyKey: 'private-runtime-grounding-rejected',
        );

        $first = app(ConversationRuntime::class)->private($input);
        $replay = app(ConversationRuntime::class)->private($input);

        self::assertSame('failed', $first->status);
        self::assertSame('ai_evidence_insufficient', $first->code);
        self::assertSame($first->toArray(), $replay->toArray());
        self::assertSame(1, $gateway->calls);
        $this->assertDatabaseHas('ai_messages', [
            'id' => $first->responseMessageId,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('ai_message_failures', [
            'message_id' => $first->responseMessageId,
            'failure_code' => 'ai_evidence_insufficient',
        ]);
        self::assertNull(DB::table('ai_executions')
            ->where('correlation_id', $first->requestMessageId)
            ->value('response_message_id'));
    }

    public function test_public_and_private_runtimes_resolve_exact_tenants_despite_a_different_selected_public_city(): void
    {
        [$privateTenant, , $privateInput] = $this->privateCatalogFixture();
        $privateTenantCity = City::factory()->create();
        $selectedCity = City::factory()->create();
        DB::table('tenants')->where('id', $privateTenant->id)->update(['city_id' => $privateTenantCity->id]);
        $originalRequest = $this->app->make('request');
        $consoleProperty = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $originalConsoleState = $consoleProperty->getValue($this->app);
        $session = $this->app->make('session')->driver();
        $session->start();
        $session->put('user_selected_city_id', $selectedCity->id);
        $request = Request::create('/api/v1/tenant/agent/chat', 'POST');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $consoleProperty->setValue($this->app, false);

        try {
            $privateResponse = app(ConversationRuntime::class)->private($privateInput);
            self::assertSame((string) $privateTenant->id, DB::table('ai_conversations')
                ->where('id', $privateResponse->conversationId)
                ->value('tenant_id'));

            app(TenantContext::class)->reset();
            [$platform] = $this->publicCatalogFixture();
            $platformCity = City::factory()->create();
            DB::table('tenants')->where('id', $platform->id)->update(['city_id' => $platformCity->id]);
            $publicResponse = app(ConversationRuntime::class)->public(new PublicConversationInput(
                message: 'show published products',
                idempotencyKey: 'public-runtime-location-scope',
                actorId: str_repeat('a', 64),
                rateLimitIdentity: '198.51.100.20',
            ));

            self::assertSame((string) $platform->id, DB::table('ai_conversations')
                ->where('id', $publicResponse->conversationId)
                ->value('tenant_id'));
        } finally {
            $consoleProperty->setValue($this->app, $originalConsoleState);
            $this->app->instance('request', $originalRequest);
        }
    }

    /** @return array{Tenant, Product, PersistingConversationGateway} */
    private function publicCatalogFixture(): array
    {
        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        $merchant = Tenant::factory()->create([
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        GlobalMarketFixture::assignCanada($merchant);
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Public runtime fixture product',
            'price' => 25,
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $platform->id,
            capability: 'public_discovery',
            audience: 'public',
            outputSchema: 'public_discovery.answer.v1',
            marketCountryCode: 'CA',
        );
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            $selection->adapterKey => [
                $selection->secretReference => new FakeAiProvider,
            ],
        ]));
        $gateway = new PersistingConversationGateway([
            'answer' => 'Use exact evidence.',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.$product->id,
                'reason' => 'Published product.',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);
        app(TenantContext::class)->reset();

        return [$platform, $product, $gateway];
    }

    /** @return array{Tenant, Product, PrivateConversationInput, PersistingConversationGateway} */
    private function privateCatalogFixture(?string $workforceRole = 'manager'): array
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Runtime catalog product',
            'is_active' => true,
            'is_visible' => true,
        ]);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $tenant->id,
            capability: 'private_tenant_agent',
            audience: 'private',
            outputSchema: 'private_agent.evidence_selection.v1',
            marketCountryCode: 'CA',
            workforceRole: $workforceRole,
        );
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            $selection->adapterKey => [
                $selection->secretReference => new FakeAiProvider,
            ],
        ]));
        $gateway = new PersistingConversationGateway([
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.$product->id,
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $input = new PrivateConversationInput(
            tenantId: (string) $tenant->id,
            actorId: 'owner-runtime-1',
            message: 'Show my catalog',
            idempotencyKey: 'private-runtime-1',
            channel: 'web',
        );

        return [$tenant, $product, $input, $gateway];
    }

    /** @return array{ai_agent_definitions: int, ai_agent_versions: int, ai_release_decisions: int, ai_agent_activations: int} */
    private function registryFixtureCounts(): array
    {
        return [
            'ai_agent_definitions' => DB::table('ai_agent_definitions')->count(),
            'ai_agent_versions' => DB::table('ai_agent_versions')->count(),
            'ai_release_decisions' => DB::table('ai_release_decisions')->count(),
            'ai_agent_activations' => DB::table('ai_agent_activations')->count(),
        ];
    }
}

final class PersistingConversationGateway implements AiGateway
{
    public int $calls = 0;

    /** @var list<string> */
    public array $allowedToolsAtExecution = [];

    public ?string $executionTenantId = null;

    public ?string $agentVersionAtExecution = null;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        $this->allowedToolsAtExecution = $request->context->allowedTools;
        $this->executionTenantId = $request->context->tenantId;
        $this->calls++;
        $this->agentVersionAtExecution = DB::table('ai_conversations')
            ->where('id', $request->context->conversationId)
            ->value('agent_version_id');
        DB::table('ai_executions')->insert([
            'correlation_id' => $request->context->correlationId,
            'tenant_id' => $request->context->tenantId,
            'billing_tenant_id' => $request->context->billingTenantId,
            'actor_id' => $request->context->actorId,
            'audience' => $request->context->audience,
            'capability' => $request->context->capability,
            'operation' => $request->operation,
            'provider' => $request->selection->providerKey,
            'model' => $request->selection->modelKey,
            'request_hash' => hash('sha256', $request->userInput),
            'claim_token' => hash('sha256', 'runtime-claim'),
            'claim_version' => 1,
            'status' => 'completed',
            'encryption_key_version' => 'v1',
            'retention_expires_at' => now()->addDay(),
            'processing_expires_at' => now()->addMinute(),
            'provider_completed_at' => now(),
            'completed_at' => now(),
            'conversation_id' => $request->context->conversationId,
            'request_message_id' => $request->context->requestMessageId,
            'provider_version_id' => $request->selection->providerVersionId,
            'model_version_id' => $request->selection->modelVersionId,
            'agent_version_id' => $request->selection->agentVersionId,
            'prompt_version_id' => $request->selection->promptVersionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new AiExecutionResult(
            correlationId: $request->context->correlationId,
            provider: $request->selection->providerKey,
            model: $request->selection->modelKey,
            data: $this->data,
            usage: [
                'input_tokens' => 10,
                'output_tokens' => 5,
                'cost_usd' => 0.01,
                'charged_cost_usd' => 0.01,
                'provider_cost_usd' => 0.01,
            ],
            latencyMilliseconds: 10,
        );
    }
}

final class ThrowingConversationGateway implements AiGateway
{
    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        throw new AiProviderUnavailableException('SECRET PROVIDER DETAIL');
    }
}

final class PolicyDeniedConversationGateway implements AiGateway
{
    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        throw new AiExecutionDeniedException('authorization');
    }
}

final class BuggingConversationGateway implements AiGateway
{
    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        throw new \RuntimeException('SECRET PROGRAMMING BUG');
    }
}
