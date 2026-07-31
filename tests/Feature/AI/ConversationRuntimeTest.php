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
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    }

    public function test_public_runtime_uses_only_the_configured_platform_owner(): void
    {
        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        $merchant = Tenant::factory()->create(['is_active' => true]);
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Public runtime product',
            'price' => 25,
            'is_active' => true,
            'is_visible' => true,
        ]);
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

    /** @return array{Tenant, Product, PrivateConversationInput, PersistingConversationGateway} */
    private function privateCatalogFixture(): array
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
}

final class PersistingConversationGateway implements AiGateway
{
    public int $calls = 0;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        $this->calls++;
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
        throw new \RuntimeException('SECRET PROVIDER DETAIL');
    }
}
