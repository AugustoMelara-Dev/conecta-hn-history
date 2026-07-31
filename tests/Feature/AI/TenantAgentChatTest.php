<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class TenantAgentChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::created(static function (Tenant $tenant): void {
            GlobalMarketFixture::assignCanada($tenant);
        });
    }

    public function test_tenant_agent_chat_is_deny_by_default(): void
    {
        config([
            'ai.private.enabled' => false,
            'ai.private.tenant_allowlist' => [],
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create([
            'slug' => 'cafe-norte',
            'is_active' => true,
        ]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'cuanto vendí hoy',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Private AI is not enabled for this tenant.');
    }

    public function test_tenant_agent_chat_requires_authentication(): void
    {
        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'cuanto vendi hoy',
        ])->assertUnauthorized();
    }

    public function test_tenant_agent_chat_answers_private_sales_only_for_authorized_allowlisted_tenant(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'slug' => 'cafe-privado',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->pro()->forUser($otherOwner)->create([
            'slug' => 'otro-cafe',
            'is_active' => true,
        ]);

        $order = Order::factory()->forTenant($tenant)->create([
            'total' => 275,
            'created_at' => now(),
        ]);

        Order::factory()->forTenant($otherTenant)->create([
            'total' => 999,
            'created_at' => now(),
        ]);
        $this->insertVerifiedRevenue((string) $tenant->getKey(), (string) $order->getKey(), 27500);
        $this->insertVerifiedRevenue((string) $otherTenant->getKey(), 'other-order', 99900);

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $tenant->id,
            'message' => 'cuanto vendí hoy',
        ])->assertOk()
            ->assertJsonStructure([
                'conversation_id',
                'request_message_id',
                'response_message_id',
                'status',
                'code',
            ])
            ->assertJsonPath('source', 'private_tenant_scope')
            ->assertJsonPath('tenant_id', (string) $tenant->id)
            ->assertJsonFragment(['answer' => 'Hoy tenes 1 ordenes registradas e ingresos netos verificados por L. 275.00.'])
            ->assertJsonPath('data.verified_net_revenue', 275)
            ->assertJsonMissing(['999']);
    }

    public function test_grounded_catalog_uses_trusted_tenant_selects_verified_items_and_only_catalog_tool(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $other = Tenant::factory()->create();
        $selected = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe seleccionado',
            'stock_quantity' => 7,
        ]);
        Product::factory()->forTenant($other)->create([
            'name' => 'PRODUCTO SECRETO AJENO',
            'stock_quantity' => 999,
        ]);

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $this->makePrivateRegistryEligible($tenant);
        $gateway = new RecordingPrivateGateway([
            'answer' => 'Invented private wording',
            'evidence' => [[
                'type' => 'product',
                'id' => 'product:'.$selected->id,
                'reason' => 'Invented reason',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => (string) $other->id,
            'message' => 'mostrame el catálogo',
            'provider' => 'attacker',
            'tools' => ['commerce.order.create'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', false)
            ->assertJsonPath('tenant_id', (string) $tenant->id)
            ->assertJsonPath('data.items.0.id', 'product:'.$selected->id)
            ->assertJsonPath('data.items.0.availability', 'available')
            ->assertJsonMissing(['stock_quantity' => 7])
            ->assertJsonMissing(['answer' => 'Invented private wording'])
            ->assertJsonMissing(['reason' => 'Invented reason'])
            ->assertJsonMissing(['name' => 'PRODUCTO SECRETO AJENO']);

        self::assertNotNull($gateway->request);
        self::assertSame((string) $tenant->id, $gateway->request->context->tenantId);
        self::assertSame((string) $tenant->id, $gateway->request->context->billingTenantId);
        self::assertSame(['tenant_catalog.read'], $gateway->request->requestedTools);
        self::assertSame(['tenant_catalog.read'], $gateway->request->context->allowedTools);
        self::assertStringNotContainsString('PRODUCTO SECRETO AJENO', $gateway->request->userInput);
        self::assertStringNotContainsString('"stock_quantity":7', $gateway->request->userInput);
    }

    public function test_real_gateway_charges_once_and_rejects_idempotency_collision(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'is_active' => true,
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Cafe molido']);

        config([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['testing'],
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
            'ai.providers.fake.enabled' => true,
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
            'ai.capabilities.private_tenant_agent.models' => ['fake-1'],
            'ai.capabilities.private_tenant_agent.max_cost_usd' => 0.02,
            'ai.capabilities.private_tenant_agent.requests_per_minute' => 100,
        ]);
        $this->makePrivateRegistryEligible($tenant);
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: [
                'evidence' => [[
                    'type' => 'product',
                    'id' => 'product:'.$product->id,
                ]],
            ],
            model: 'fake-1',
            inputTokens: 20,
            outputTokens: 10,
            costUsd: 0.01,
            finishReason: 'stop',
        ));
        $this->app->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'fake_structured_text' => ['fake.primary' => $provider],
        ]));
        $this->app->forgetInstance(AiGateway::class);
        Sanctum::actingAs($owner);

        $headers = ['Idempotency-Key' => 'private-catalog-1'];
        $response = $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'mostrame el catalogo',
        ]);
        $replay = $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'mostrame el catalogo',
        ]);
        $conflict = $this->withHeaders($headers)->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'que productos tenes',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('fallback', false)
            ->assertJsonPath('data.items.0.id', 'product:'.$product->id)
            ->assertJsonPath('meta.provider', 'fake');
        $replay->assertOk()->assertJsonPath('fallback', false);
        $conflict->assertConflict()
            ->assertJsonPath('code', 'ai_request_conflict')
            ->assertJsonMissing(['answer']);
        self::assertSame($response->json('meta.correlation_id'), $replay->json('meta.correlation_id'));
        self::assertCount(1, $provider->requests);
        self::assertEqualsWithDelta(0.99, (float) $tenant->fresh()->ai_balance, 0.000001);
        $this->assertDatabaseHas('ai_credit_reservations', [
            'tenant_id' => (string) $tenant->id,
            'status' => 'settled',
        ]);
    }

    public function test_appointment_grounding_omits_customer_identity_contact_and_notes(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $appointment = Appointment::withoutEvents(fn (): Appointment => Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'scheduled_at' => today()->setTime(12, 0),
            'end_at' => today()->setTime(13, 0),
            'customer_name' => 'SECRET CUSTOMER',
            'customer_email' => 'secret@example.test',
            'customer_phone' => '+504 SECRET PHONE',
            'customer_notes' => 'SECRET NOTES',
            'staff_notes' => 'SECRET STAFF NOTES',
        ]));

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $gateway = new RecordingPrivateGateway([
            'answer' => 'Una cita verificada.',
            'evidence' => [[
                'type' => 'appointment',
                'id' => 'appointment:'.$appointment->id,
                'reason' => 'Horario verificado.',
            ]],
        ]);
        $this->app->instance(AiGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'que citas tengo hoy',
        ])->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('data.appointments_count', 1);

        self::assertSame(0, $gateway->calls);
        self::assertNull($gateway->request);
        foreach (['SECRET CUSTOMER', 'secret@example.test', 'SECRET PHONE', 'SECRET NOTES', 'SECRET STAFF NOTES'] as $secret) {
            self::assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertDatabaseCount('ai_executions', 0);
        $this->assertDatabaseCount('ai_credit_reservations', 0);
    }

    public function test_unknown_private_intent_does_not_call_gateway_or_spend_credits(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $gateway = new RecordingPrivateGateway(['answer' => 'unused', 'evidence' => []]);
        $this->app->instance(AiGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'hola como estas',
        ])->assertOk()->assertJsonPath('data.intent', 'unknown');

        self::assertSame(0, $gateway->calls);
        $this->assertDatabaseCount('ai_executions', 0);
        $this->assertDatabaseCount('ai_credit_reservations', 0);
    }

    public function test_empty_catalog_does_not_call_gateway_or_spend_credits(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $gateway = new RecordingPrivateGateway(['answer' => 'unused', 'evidence' => []]);
        $this->app->instance(AiGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'mostrame el catálogo',
        ])->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonCount(0, 'data.items');

        self::assertSame(0, $gateway->calls);
        $this->assertDatabaseCount('ai_executions', 0);
        $this->assertDatabaseCount('ai_credit_reservations', 0);
    }

    public function test_availability_uses_only_coarse_stock_and_falls_back_on_provider_outage(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        Product::factory()->forTenant($tenant)->outOfStock()->create([
            'name' => 'Producto agotado',
            'stock_quantity' => 0,
        ]);
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $this->makePrivateRegistryEligible($tenant);
        $gateway = new FailingPrivateGateway;
        $this->app->instance(AiGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'que disponibilidad tenes',
        ])->assertOk()
            ->assertJsonPath('fallback', true)
            ->assertJsonPath('data.items.0.availability', 'unavailable')
            ->assertJsonMissing(['stock_quantity' => 0]);

        self::assertSame(1, $gateway->calls);
        self::assertNotNull($gateway->request);
        self::assertSame(['tenant_availability.read'], $gateway->request->requestedTools);
        self::assertStringNotContainsString('"stock_quantity":0', $gateway->request->userInput);
        self::assertStringNotContainsString('SECRET PROVIDER DETAIL', $response->getContent());
    }

    public function test_slug_only_private_allowlist_no_longer_grants_access(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->slug],
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'cuanto vendi hoy',
        ])->assertForbidden();
    }

    public function test_invalid_private_evidence_always_returns_canonical_fallback(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['is_active' => true]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $product = Product::factory()->forTenant($tenant)->create();
        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $tenant->id],
        ]);
        $this->makePrivateRegistryEligible($tenant);
        Sanctum::actingAs($owner);
        $validId = 'product:'.$product->id;
        $invalidSets = [
            [['type' => 'product', 'id' => 'product:unknown', 'reason' => 'unknown']],
            [['type' => 'service', 'id' => $validId, 'reason' => 'wrong type']],
            [
                ['type' => 'product', 'id' => $validId, 'reason' => 'one'],
                ['type' => 'product', 'id' => $validId, 'reason' => 'duplicate'],
            ],
        ];

        foreach ($invalidSets as $index => $evidence) {
            $this->app->instance(AiGateway::class, new RecordingPrivateGateway([
                'answer' => 'Invalid private answer '.$index,
                'evidence' => $evidence,
            ]));

            $this->postJson('/api/v1/tenant/agent/chat', [
                'message' => 'mostrame el catalogo',
            ])->assertOk()
                ->assertJsonPath('fallback', true)
                ->assertJsonMissing(['answer' => 'Invalid private answer '.$index]);
        }
    }

    public function test_tenant_agent_chat_rejects_cross_tenant_body_even_when_target_is_allowlisted(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create([
            'slug' => 'tenant-propio',
            'is_active' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $otherOwner = User::factory()->create();
        $otherTenant = Tenant::factory()->pro()->forUser($otherOwner)->create([
            'slug' => 'tenant-ajeno',
            'is_active' => true,
        ]);

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $otherTenant->id],
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tenant/agent/chat', [
            'tenant_id' => $otherTenant->id,
            'message' => 'cuanto vendi hoy',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Private AI is not enabled for this tenant.');
    }

    private function insertVerifiedRevenue(string $tenantId, string $aggregateId, int $amountMinor): void
    {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenantId,
            'aggregate_type' => 'order',
            'aggregate_id' => $aggregateId,
            'outcome' => CommercialOutcome::OrderPaid->value,
            'projection_key' => "{$tenantId}:order:{$aggregateId}:paid-test",
            'amount_minor' => $amountMinor,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePrivateRegistryEligible(Tenant $tenant): void
    {
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $tenant->getKey(),
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
    }
}

final class FailingPrivateGateway implements AiGateway
{
    public int $calls = 0;

    public ?AiExecutionRequest $request = null;

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        $this->calls++;
        $this->request = $request;

        throw new \RuntimeException('SECRET PROVIDER DETAIL');
    }
}

final class RecordingPrivateGateway implements AiGateway
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
            'claim_token' => hash('sha256', 'recording-private-gateway'),
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
