<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\InsufficientAiCreditsException;
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

final class PublicGroundedDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::created(static function (Tenant $tenant): void {
            GlobalMarketFixture::assignCanada($tenant);
        });
    }

    public function test_enabled_generation_uses_only_canonical_evidence_and_server_context(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Seguro',
            'slug' => 'cafe-seguro',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe molido',
            'description' => 'Tueste local',
            'price' => 140,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $billingTenant = (string) $this->eligiblePlatform()->id;
        config()->set('ai.public.billing_tenant_id', $billingTenant);
        config()->set('ai.limits.max_user_input_characters', 700);
        $gateway = new RecordingPublicGateway([
            'answer' => 'El cafe molido encaja en tu presupuesto.',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.$product->id,
                'reason' => 'Cuesta menos de L. 150.00.',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'cafe con 150 lempiras',
            'billing_tenant_id' => (string) Str::uuid(),
            'provider' => 'attacker',
            'model' => 'attacker-model',
            'correlation_id' => (string) Str::uuid(),
            'tools' => ['commerce.order.create'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', false)
            ->assertJsonPath('meta.llm_used', true)
            ->assertJsonPath('meta.provider', 'fake')
            ->assertJsonFragment([
                'evidence_id' => 'product:'.$product->id,
                'name' => 'Cafe molido',
            ]);

        self::assertSame(1, $gateway->calls);
        self::assertNotNull($gateway->request);
        self::assertSame($billingTenant, $gateway->request->context->tenantId);
        self::assertSame($billingTenant, $gateway->request->context->billingTenantId);
        self::assertSame(['public_catalog.search'], $gateway->request->requestedTools);
        self::assertSame(['public_catalog.search'], $gateway->request->context->allowedTools);
        self::assertStringContainsString('product:'.$product->id, $gateway->request->userInput);
        self::assertLessThanOrEqual(700, mb_strlen($gateway->request->userInput));
        self::assertStringNotContainsString('stock_quantity', $gateway->request->userInput);
    }

    public function test_real_gateway_charges_only_the_server_configured_platform_tenant(): void
    {
        $platform = Tenant::factory()->create([
            'name' => 'Conecta HN AI',
            'slug' => 'conecta-hn-ai',
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0,
        ]);
        $business = Tenant::factory()->create([
            'name' => 'Artesania Viva',
            'slug' => 'artesania-viva',
            'is_active' => true,
            'subscription_status' => 'active',
            'ai_balance' => 0.5,
            'ai_reserved_balance' => 0,
        ]);
        $product = Product::factory()->forTenant($business)->create([
            'name' => 'Taza artesanal',
            'price' => 180,
            'is_active' => true,
            'is_visible' => true,
        ]);

        config()->set([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['testing'],
            'ai.providers.fake.enabled' => true,
            'ai.public.billing_tenant_id' => (string) $platform->getKey(),
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'fake',
            'ai.capabilities.public_discovery.models' => ['fake-1'],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 100,
        ]);

        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: [
                'answer' => 'Tiene 70% de descuento, entrega gratis y abre 24 horas.',
                'evidence' => [[
                    'type' => 'product',
                    'id' => 'product:'.$product->id,
                    'reason' => 'Incluye entrega gratis y garantia de por vida.',
                ]],
            ],
            model: 'fake-1',
            inputTokens: 20,
            outputTokens: 10,
            costUsd: 0.01,
            finishReason: 'stop',
        ));
        AiEligibleRegistryFixture::create(
            tenantId: (string) $platform->id,
            capability: 'public_discovery',
            audience: 'public',
            outputSchema: 'public_discovery.answer.v1',
            marketCountryCode: 'CA',
        );
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => ['fake.primary' => $provider],
        ]));
        $this->app->forgetInstance(AiGateway::class);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'taza con 200 lempiras',
            'billing_tenant_id' => (string) $business->getKey(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', false)
            ->assertJsonPath('meta.provider', 'fake')
            ->assertJsonFragment(['evidence_id' => 'product:'.$product->id]);

        self::assertStringNotContainsString('70% de descuento', $response->getContent());
        self::assertStringNotContainsString('entrega gratis', $response->getContent());
        self::assertStringNotContainsString('garantia de por vida', $response->getContent());
        self::assertStringContainsString('Taza artesanal', $response->getContent());
        self::assertStringContainsString('L. 180.00', $response->getContent());

        self::assertCount(1, $provider->requests);
        self::assertEqualsWithDelta(0.99, (float) $platform->fresh()->ai_balance, 0.000001);
        self::assertEqualsWithDelta(0.5, (float) $business->fresh()->ai_balance, 0.000001);
        $this->assertDatabaseHas('ai_credit_reservations', [
            'tenant_id' => (string) $platform->getKey(),
            'status' => 'settled',
        ]);
        $this->assertDatabaseHas('ai_executions', [
            'tenant_id' => (string) $platform->getKey(),
            'billing_tenant_id' => (string) $platform->getKey(),
            'status' => 'completed',
        ]);
        self::assertFalse(app(TenantContext::class)->isInitialized());
    }

    public function test_enabled_public_ai_fails_closed_when_platform_tenant_is_missing(): void
    {
        config()->set([
            'ai.enabled' => true,
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.public.billing_tenant_id' => null,
        ]);

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'cafe con 150 lempiras',
        ])->assertStatus(503);

        self::assertFalse(app(TenantContext::class)->isInitialized());
    }

    public function test_unknown_model_evidence_falls_back_without_leaking_invented_content(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Pan Real',
            'slug' => 'pan-real',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        Product::factory()->forTenant($tenant)->create([
            'name' => 'Pan de coco',
            'description' => 'Horneado hoy',
            'price' => 35,
            'is_active' => true,
            'is_visible' => true,
        ]);

        config()->set('ai.public.billing_tenant_id', (string) $this->eligiblePlatform()->id);
        $gateway = new RecordingPublicGateway([
            'answer' => 'Invented claim',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:999999',
                'reason' => 'Invented reason',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'pan con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('meta.llm_used', false)
            ->assertJsonFragment(['name' => 'Pan de coco'])
            ->assertJsonMissing(['answer' => 'Invented claim'])
            ->assertJsonMissing(['reason' => 'Invented reason']);
    }

    public function test_duplicate_or_type_mismatched_evidence_is_rejected(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Tienda Canonica',
            'slug' => 'tienda-canonica',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Miel local',
            'price' => 90,
            'is_active' => true,
            'is_visible' => true,
        ]);

        config()->set('ai.public.billing_tenant_id', (string) $this->eligiblePlatform()->id);
        $invalidEvidenceSets = [
            [
                ['type' => 'product', 'id' => 'product:'.$product->id, 'reason' => 'Primera'],
                ['type' => 'product', 'id' => 'product:'.$product->id, 'reason' => 'Duplicada'],
            ],
            [
                ['type' => 'service', 'id' => 'product:'.$product->id, 'reason' => 'Tipo incorrecto'],
            ],
        ];

        foreach ($invalidEvidenceSets as $index => $evidence) {
            $this->app->instance(AiGateway::class, new RecordingPublicGateway([
                'answer' => 'Invalid model answer '.$index,
                'evidence' => $evidence,
            ]));

            $response = $this->postJson('/api/v1/directory/guide/chat', [
                'message' => 'miel con 100 lempiras',
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('fallback', true)
                ->assertJsonFragment(['name' => 'Miel local'])
                ->assertJsonMissing(['answer' => 'Invalid model answer '.$index]);
        }
    }

    public function test_budget_exhaustion_returns_a_safe_deterministic_fallback(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Mercado Local',
            'slug' => 'mercado-local',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        Product::factory()->forTenant($tenant)->create([
            'name' => 'Fruta fresca',
            'price' => 25,
            'is_active' => true,
            'is_visible' => true,
        ]);

        config()->set('ai.public.billing_tenant_id', (string) $this->eligiblePlatform()->id);
        $this->app->instance(AiGateway::class, new FailingPublicGateway(
            new InsufficientAiCreditsException('SECRET budget detail'),
        ));

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'fruta con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('meta.llm_used', false)
            ->assertJsonFragment(['name' => 'Fruta fresca']);

        self::assertStringNotContainsString('SECRET', $response->getContent());
    }

    public function test_prompt_that_cannot_fit_even_one_candidate_falls_back_before_gateway(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => str_repeat('Negocio Largo ', 10),
            'slug' => 'negocio-largo',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        Product::factory()->forTenant($tenant)->create([
            'name' => 'Catalogo '.str_repeat('largo ', 15),
            'description' => str_repeat('descripcion extensa ', 20),
            'price' => 100,
            'is_active' => true,
            'is_visible' => true,
        ]);

        config()->set('ai.public.billing_tenant_id', (string) $this->eligiblePlatform()->id);
        config()->set('ai.limits.max_user_input_characters', 512);
        $gateway = new RecordingPublicGateway([
            'answer' => 'Must not run',
            'evidence' => [],
        ]);
        $this->app->instance(AiGateway::class, $gateway);

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'catalogo con 150 lempiras',
        ])->assertOk()->assertJsonPath('fallback', true);

        self::assertSame(0, $gateway->calls);
    }

    public function test_no_candidates_never_calls_the_ai_gateway(): void
    {
        config()->set('ai.public.billing_tenant_id', (string) Tenant::factory()->create()->id);
        $gateway = new RecordingPublicGateway([
            'answer' => 'Must not run',
            'evidence' => [],
        ]);
        $this->app->instance(AiGateway::class, $gateway);

        $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'producto inexistente',
            'budget' => 1,
        ])->assertOk()->assertJsonPath('items', []);

        self::assertSame(0, $gateway->calls);
        $this->assertDatabaseCount('ai_executions', 0);
    }

    private function eligiblePlatform(): Tenant
    {
        $platform = Tenant::factory()->create([
            'ai_balance' => 10,
            'ai_reserved_balance' => 0,
        ]);
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

        return $platform->refresh();
    }
}

final class RecordingPublicGateway implements AiGateway
{
    public int $calls = 0;

    public ?AiExecutionRequest $request = null;

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        $this->calls++;
        $this->request = $request;
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
            'claim_token' => hash('sha256', 'recording-public-gateway'),
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
                'cost_usd' => 0.001,
                'charged_cost_usd' => 0.001,
                'provider_cost_usd' => 0.001,
            ],
            latencyMilliseconds: 5,
        );
    }
}
final class FailingPublicGateway implements AiGateway
{
    public function __construct(private readonly \Throwable $exception) {}

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        throw $this->exception;
    }
}
