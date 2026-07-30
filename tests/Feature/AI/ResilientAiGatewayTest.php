<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\AiExecutionContextFactory;
use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Application\Ai\Policies\AiCapabilityPolicy;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Exceptions\Ai\AiCircuitOpenException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiExecutionFailedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\AiExecutionStore;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Infrastructure\AI\LaravelStructuredOutputValidator;
use App\Infrastructure\AI\ResilientAiGateway;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\TestCase;

final class ResilientAiGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('unused');
        config()->set([
            'ai.enabled' => true,
            'ai.commercial_credits.credits_per_provider_usd' => 1.0,
            'ai.allowed_environments' => ['testing'],
            'ai.private.enabled' => true,
            'ai.providers.fake.enabled' => true,
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.capabilities.private_tenant_agent.audience' => 'private',
            'ai.capabilities.private_tenant_agent.billing_mode' => 'tenant',
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
            'ai.capabilities.private_tenant_agent.models' => ['fake-1'],
            'ai.capabilities.private_tenant_agent.requires_tenant' => true,
            'ai.capabilities.private_tenant_agent.max_cost_usd' => 0.20,
            'ai.capabilities.private_tenant_agent.requests_per_minute' => 100,
            'ai.capabilities.private_tenant_agent.tools' => ['tenant_catalog.read', 'tenant_lead.prepare'],
            'ai.tools.tenant_catalog.read.risk' => 'read',
            'ai.tools.tenant_lead.prepare.risk' => 'mutation',
            'ai.tools.commerce.order.create.risk' => 'economic',
            'ai.schemas' => [
                'private_agent.answer.v1' => [
                    'capability' => 'private_tenant_agent',
                    'rules' => ['answer' => ['required', 'string', 'max:500']],
                ],
            ],
            'ai.circuit.failure_threshold' => 2,
            'ai.circuit.cooldown_seconds' => 60,
        ]);
    }

    public function test_global_kill_switch_denies_before_provider_budget_or_claim(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        config()->set('ai.enabled', false);

        $this->expectDenied('global_kill_switch', fn () => $this->gateway($provider)->execute($this->request($tenant)));

        self::assertSame([], $provider->requests);
        self::assertSame(0, DB::table('ai_credit_reservations')->count());
        self::assertSame(0, DB::table('ai_executions')->count());
    }

    public function test_policy_uses_real_environment_and_binds_capability_to_audience(): void
    {
        $tenant = $this->tenant();
        $policy = new AiCapabilityPolicy;
        config()->set('ai.allowed_environments', ['spoofed']);

        $this->expectDenied('environment', fn () => $policy->assertExecutionAllowed($this->request($tenant)->context));

        config()->set('ai.allowed_environments', ['testing']);
        $this->expectDenied(
            'capability_audience',
            fn () => $policy->assertExecutionAllowed($this->request($tenant, audience: 'public')->context),
        );
    }

    public function test_public_billing_tenant_is_derived_and_enforced_authoritatively(): void
    {
        $platform = $this->tenant();
        $victim = $this->tenant();
        config()->set([
            'ai.public.billing_tenant_id' => (string) $platform->getKey(),
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.audience' => 'public',
            'ai.capabilities.public_discovery.billing_mode' => 'platform',
            'ai.capabilities.public_discovery.provider' => 'fake',
            'ai.capabilities.public_discovery.models' => ['fake-1'],
            'ai.capabilities.public_discovery.requires_tenant' => false,
            'ai.capabilities.public_discovery.max_cost_usd' => 0.20,
            'ai.capabilities.public_discovery.requests_per_minute' => 10,
        ]);
        $policy = new AiCapabilityPolicy;
        $spoofed = new AiExecutionContext(
            correlationId: (string) Str::uuid(),
            audience: 'public',
            capability: 'public_discovery',
            tenantId: null,
            billingTenantId: (string) $victim->getKey(),
            actorId: null,
            authorized: true,
            entitled: true,
            rateLimitSubject: hash('sha256', 'visitor'),
        );

        $this->expectDenied('public_billing_tenant', fn () => $policy->assertExecutionAllowed($spoofed));

        $derived = (new AiExecutionContextFactory)->create(
            capability: 'public_discovery',
            rateLimitIdentity: 'trusted-ip-and-session',
            authorized: true,
            entitled: true,
        );
        self::assertSame((string) $platform->getKey(), $derived->tenantId);
        self::assertSame((string) $platform->getKey(), $derived->billingTenantId);
        $policy->assertExecutionAllowed($derived);
        self::assertSame('public', $derived->audience);
    }

    public function test_internal_tenant_capability_cannot_bill_another_tenant(): void
    {
        $tenant = $this->tenant();
        $victim = $this->tenant();
        config()->set([
            'ai.capabilities.catalog_vision.enabled' => true,
            'ai.capabilities.catalog_vision.audience' => 'internal',
            'ai.capabilities.catalog_vision.billing_mode' => 'tenant',
            'ai.capabilities.catalog_vision.provider' => 'fake',
            'ai.capabilities.catalog_vision.models' => ['fake-1'],
            'ai.capabilities.catalog_vision.requires_tenant' => true,
            'ai.capabilities.catalog_vision.max_cost_usd' => 0.20,
            'ai.capabilities.catalog_vision.requests_per_minute' => 10,
        ]);
        $spoofed = new AiExecutionContext(
            correlationId: (string) Str::uuid(),
            audience: 'internal',
            capability: 'catalog_vision',
            tenantId: (string) $tenant->getKey(),
            billingTenantId: (string) $victim->getKey(),
            actorId: null,
            authorized: true,
            entitled: true,
            rateLimitSubject: hash('sha256', 'worker'),
        );

        $this->expectDenied('billing_tenant', fn () => (new AiCapabilityPolicy)->assertExecutionAllowed($spoofed));

        $derived = (new AiExecutionContextFactory)->create(
            capability: 'catalog_vision',
            rateLimitIdentity: 'trusted-worker',
            tenantId: (string) $tenant->getKey(),
            authorized: true,
            entitled: true,
        );
        self::assertSame((string) $tenant->getKey(), $derived->billingTenantId);
        (new AiCapabilityPolicy)->assertExecutionAllowed($derived);
        self::assertSame('internal', $derived->audience);
    }

    public function test_private_gates_are_authoritative_and_independent(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;

        $this->expectDenied('authorization', fn () => $this->gateway($provider)->execute(
            $this->request($tenant, authorized: false),
        ));
        $this->expectDenied('entitlement', fn () => $this->gateway($provider)->execute(
            $this->request($tenant, entitled: false),
        ));
        $this->expectDenied('tenant_allowlist', fn () => $this->gateway($provider)->execute(
            $this->request($tenant, allowlisted: false),
        ));

        config()->set('ai.private.enabled', false);
        $this->expectDenied('private_kill_switch', fn () => $this->gateway($provider)->execute(
            $this->request($tenant),
        ));

        self::assertSame([], $provider->requests);
    }

    public function test_enabled_capability_keeps_bootstrap_rate_policy_but_ignores_config_model_and_cost_routing(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        config()->set('ai.capabilities.private_tenant_agent.requests_per_minute', 0);
        $this->expectDenied('invalid_rate_policy', fn () => $this->gateway($provider)->execute($this->request($tenant)));

        config()->set('ai.capabilities.private_tenant_agent.requests_per_minute', 10);
        config()->set('ai.capabilities.private_tenant_agent.models', ['']);
        config()->set('ai.capabilities.private_tenant_agent.max_cost_usd', 0);
        $provider->queueResult($this->providerResult());
        $this->gateway($provider)->execute($this->request($tenant));

        self::assertSame(1, DB::table('ai_executions')->count());
    }

    public function test_success_is_charged_audited_and_replayed_without_another_provider_call(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult(cost: 0.10));
        $request = $this->request($tenant);

        $first = $this->gateway($provider)->execute($request);
        $second = $this->gateway($provider)->execute($request);

        self::assertSame($first->data, $second->data);
        self::assertSame($first->usage, $second->usage);
        self::assertSame(1, count($provider->requests));
        self::assertSame(1, DB::table('ai_credit_reservations')->count());
        self::assertSame('settled', DB::table('ai_credit_reservations')->value('status'));
        self::assertSame('completed', DB::table('ai_executions')->value('status'));
        self::assertSame(0.99, (float) $tenant->refresh()->ai_balance);
        self::assertSame('fake-1', $provider->requests[0]->modelKey);
        $this->assertDatabaseHas('ai_executions', [
            'correlation_id' => $request->context->correlationId,
            'provider_version_id' => $request->selection->providerVersionId,
            'model_version_id' => $request->selection->modelVersionId,
            'agent_version_id' => $request->selection->agentVersionId,
            'prompt_version_id' => $request->selection->promptVersionId,
        ]);

        $ciphertext = (string) DB::table('ai_executions')->value('result_ciphertext');
        self::assertStringNotContainsString('Datos verificados', $ciphertext);
    }

    public function test_in_flight_pricing_change_uses_the_persisted_reservation_snapshot(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult(cost: 0.10));
        $provider->beforeGenerate(function (): void {
            config()->set([
                'ai.commercial_credits.pricing_version' => 'credits-v2-during-call',
                'ai.commercial_credits.credits_per_provider_usd' => 10.0,
                'ai.commercial_credits.minimum_charge' => 0.50,
            ]);
        });

        $this->gateway($provider)->execute($this->request($tenant));

        $reservation = DB::table('ai_credit_reservations')->first();
        $execution = DB::table('ai_executions')->first();
        self::assertSame('credits-v1', $reservation->pricing_version);
        self::assertSame(1.0, (float) $reservation->credits_per_provider_usd);
        self::assertSame(0.01, (float) $reservation->minimum_charge);
        self::assertSame(0.01, (float) $reservation->actual_cost);
        self::assertSame('credits-v1', $execution->credit_pricing_version);
        self::assertSame(1.0, (float) $execution->credits_per_provider_usd);
        self::assertSame(0.01, (float) $execution->credit_minimum_charge);
        self::assertSame(0.01, (float) $execution->charged_credits);
        self::assertSame(0.99, (float) $tenant->refresh()->ai_balance);
    }

    public function test_settlement_uses_exact_database_model_rates_not_provider_cost_evidence(): void
    {
        $tenant = $this->tenant();
        config()->set('ai.commercial_credits.minimum_charge', 0);
        config()->set('ai.providers.fake.models.fake-1', [
            'input_usd_per_million' => 999,
            'output_usd_per_million' => 999,
        ]);
        $request = $this->request($tenant);
        DB::table('ai_model_versions')->where('id', $request->selection->modelVersionId)->update([
            'input_cost_minor' => 100,
            'output_cost_minor' => 200,
        ]);
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'Database-priced usage.'],
            model: 'fake-1',
            inputTokens: 100,
            outputTokens: 10,
            costUsd: 0.75,
        ));

        $result = $this->gateway($provider)->execute($request);

        self::assertEqualsWithDelta(0.00012, $result->usage['charged_cost_usd'], 0.000000001);
        self::assertSame(0.75, $result->usage['provider_cost_usd']);
        self::assertEqualsWithDelta(0.00012, (float) DB::table('ai_executions')->value('charged_cost'), 0.000000001);
        self::assertSame(0.75, (float) DB::table('ai_executions')->value('provider_cost'));
        self::assertEqualsWithDelta(0.00012, (float) DB::table('ai_credit_reservations')->value('actual_cost'), 0.000000001);
        self::assertEqualsWithDelta(0.99988, (float) $tenant->fresh()->ai_balance, 0.000000001);
    }

    public function test_completed_replay_remains_available_while_provider_circuit_is_open(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult(cost: 0.10));
        $request = $this->request($tenant);
        $gateway = $this->gateway($provider);

        $first = $gateway->execute($request);
        Cache::put(
            'ai:circuit:open:'.hash('sha256', 'fake|private_tenant_agent'),
            now()->addMinute()->timestamp,
            60,
        );

        $replayed = $gateway->execute($request);

        self::assertSame($first->data, $replayed->data);
        self::assertSame(1, count($provider->requests));
        self::assertSame(1, DB::table('ai_credit_reservations')->count());
    }

    public function test_correlation_reuse_with_different_payload_is_denied(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult());
        $correlationId = (string) Str::uuid();

        $this->gateway($provider)->execute($this->request($tenant, correlationId: $correlationId));
        $this->expectDenied('idempotency_conflict', fn () => $this->gateway($provider)->execute(
            $this->request($tenant, correlationId: $correlationId, userInput: 'Different payload'),
        ));

        self::assertSame(1, count($provider->requests));
    }

    public function test_schema_failures_refund_without_opening_provider_circuit(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult(data: ['invented' => 'unsafe']));
        $provider->queueResult($this->providerResult(data: ['invented' => 'unsafe']));
        $provider->queueResult($this->providerResult());
        $gateway = $this->gateway($provider);

        $malformedFailures = 0;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $gateway->execute($this->request($tenant));
                self::fail('Malformed output must fail.');
            } catch (AiMalformedResponseException) {
                $malformedFailures++;
            }
        }

        self::assertSame(2, $malformedFailures);
        $result = $gateway->execute($this->request($tenant));
        self::assertSame('Datos verificados.', $result->data['answer']);
        self::assertSame(3, count($provider->requests));
        self::assertSame(2, DB::table('ai_credit_reservations')->where('status', 'refunded')->count());
    }

    public function test_provider_failure_is_sanitized_and_opens_atomic_provider_circuit(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueFailure(new AiProviderUnavailableException('SECRET prompt echoed by provider'));
        $provider->queueFailure(new AiProviderUnavailableException('SECRET response echoed by provider'));
        $gateway = $this->gateway($provider);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $gateway->execute($this->request($tenant, userInput: 'SECRET customer data'));
                self::fail('Provider failure must escape.');
            } catch (AiProviderUnavailableException $exception) {
                self::assertSame('AI provider is temporarily unavailable.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('SECRET', $exception->getMessage());
            }
        }

        $this->expectException(AiCircuitOpenException::class);
        $gateway->execute($this->request($tenant));
    }

    public function test_half_open_probe_recovers_on_success_and_reopens_on_later_failure(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueFailure(new AiProviderUnavailableException('first outage'));
        $provider->queueResult($this->providerResult());
        $provider->queueResult($this->providerResult());
        $provider->queueFailure(new AiProviderUnavailableException('second outage'));
        config()->set([
            'ai.circuit.failure_threshold' => 1,
            'ai.circuit.cooldown_seconds' => 1,
        ]);
        $gateway = $this->gateway($provider);

        try {
            $gateway->execute($this->request($tenant));
            self::fail('First provider outage must fail.');
        } catch (AiProviderUnavailableException) {
            self::assertSame(1, count($provider->requests));
        }

        $this->travel(2)->seconds();
        self::assertSame('Datos verificados.', $gateway->execute($this->request($tenant))->data['answer']);
        self::assertSame('Datos verificados.', $gateway->execute($this->request($tenant))->data['answer']);

        try {
            $gateway->execute($this->request($tenant));
            self::fail('Later provider outage must reopen the circuit.');
        } catch (AiProviderUnavailableException) {
            self::assertSame(4, count($provider->requests));
        }

        $this->expectException(AiCircuitOpenException::class);
        $gateway->execute($this->request($tenant));
    }

    public function test_provider_usage_overage_is_accounted_failed_and_opens_safety_circuit(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'unsafe'],
            model: 'fake-1',
            inputTokens: 801,
            outputTokens: 0,
            costUsd: 0.35,
        ));
        $gateway = $this->gateway($provider);

        try {
            $gateway->execute($this->request($tenant));
            self::fail('Provider overage must fail closed.');
        } catch (AiExecutionFailedException $exception) {
            self::assertSame('AI provider exceeded authorized resource limits.', $exception->getMessage());
        }

        self::assertSame(1.0, (float) $tenant->refresh()->ai_balance);
        self::assertSame('settled', DB::table('ai_credit_reservations')->value('status'));
        self::assertSame('failed', DB::table('ai_executions')->value('status'));
        self::assertSame('provider_input_token_overrun', DB::table('ai_executions')->value('failure_code'));
        self::assertSame(0.35, (float) DB::table('ai_executions')->value('provider_cost'));
        self::assertSame(0.0, (float) DB::table('ai_executions')->value('charged_cost'));

        $this->expectException(AiCircuitOpenException::class);
        $gateway->execute($this->request($tenant));
    }

    public function test_unauthorized_model_trips_the_safety_circuit(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'unsafe'],
            model: 'unapproved-model',
            inputTokens: 1,
            outputTokens: 1,
            costUsd: 0.01,
        ));

        try {
            $this->gateway($provider)->execute($this->request($tenant));
            self::fail('Unapproved model must fail closed.');
        } catch (AiExecutionFailedException $exception) {
            self::assertSame('AI provider exceeded authorized resource limits.', $exception->getMessage());
        }

        self::assertSame('provider_model_not_authorized', DB::table('ai_executions')->value('failure_code'));
    }

    public function test_gateway_resolves_the_adapter_from_the_exact_selection(): void
    {
        $tenant = $this->tenant();
        $selected = new FakeAiProvider('fake');
        $selected->queueResult($this->providerResult());
        $decoy = new FakeAiProvider('decoy');
        $decoy->queueResult(new AiProviderResult(
            data: ['answer' => 'wrong adapter'],
            model: 'fake-1',
            inputTokens: 1,
            outputTokens: 1,
            costUsd: 0.01,
        ));
        $request = $this->request($tenant);
        $gateway = new ResilientAiGateway(
            new ConfiguredAiProviderPool([
                'fake_structured_text' => ['fake.primary' => $selected],
                'decoy_structured_text' => ['decoy.primary' => $decoy],
            ]),
            new LaravelStructuredOutputValidator,
            new AiCapabilityPolicy,
            new BudgetEnforcer,
            new AiExecutionStore,
        );

        $result = $gateway->execute($request);

        self::assertSame('Datos verificados.', $result->data['answer']);
        self::assertCount(1, $selected->requests);
        self::assertCount(0, $decoy->requests);
    }

    public function test_secret_reference_mismatch_is_denied_before_provider_or_durable_writes(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult());

        $this->expectDenied('provider_secret_reference', fn () => $this->gateway($provider)->execute(
            $this->request($tenant, secretReference: 'secret-looking-mismatched-reference'),
        ));

        self::assertCount(0, $provider->requests);
        self::assertSame(0, DB::table('ai_executions')->count());
        self::assertSame(0, DB::table('ai_credit_reservations')->count());
    }

    public function test_output_token_overrun_is_accounted_and_failed(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'unsafe'],
            model: 'fake-1',
            inputTokens: 1,
            outputTokens: 900,
            costUsd: 0.10,
        ));

        try {
            $this->gateway($provider)->execute($this->request($tenant));
            self::fail('Output token overrun must fail closed.');
        } catch (AiExecutionFailedException) {
            self::assertSame('provider_output_token_overrun', DB::table('ai_executions')->value('failure_code'));
            self::assertSame('settled', DB::table('ai_credit_reservations')->value('status'));
        }
    }

    public function test_rate_limit_is_scoped_to_server_side_subject(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult($this->providerResult());
        $provider->queueResult($this->providerResult());
        config()->set('ai.capabilities.private_tenant_agent.requests_per_minute', 2);
        $gateway = $this->gateway($provider);

        $gateway->execute($this->request($tenant));
        $gateway->execute($this->request($tenant));
        $this->expectDenied('rate_limit', fn () => $gateway->execute($this->request($tenant)));

        self::assertSame(2, count($provider->requests));
        self::assertSame(2, DB::table('ai_executions')->count());
    }

    public function test_mutation_economic_and_unknown_tools_fail_closed_while_read_is_allowed(): void
    {
        $tenant = $this->tenant();
        $policy = new AiCapabilityPolicy;

        $this->expectDenied('tool_not_allowed', fn () => $policy->assertRequestAllowed(
            $this->request($tenant, requestedTools: ['unknown.tool']),
        ));
        $this->expectDenied('tool_requires_explicit_confirmation', fn () => $policy->assertRequestAllowed(
            $this->request($tenant, requestedTools: ['tenant_lead.prepare']),
        ));

        config()->set('ai.capabilities.private_tenant_agent.tools', ['commerce.order.create']);
        $this->expectDenied('tool_requires_explicit_confirmation', fn () => $policy->assertRequestAllowed(
            $this->request(
                $tenant,
                requestedTools: ['commerce.order.create'],
                allowedTools: ['commerce.order.create'],
            ),
        ));

        config()->set('ai.capabilities.private_tenant_agent.tools', ['tenant_catalog.read']);
        $policy->assertRequestAllowed($this->request($tenant, requestedTools: ['tenant_catalog.read']));
        self::assertSame(1, count($this->request($tenant, requestedTools: ['tenant_catalog.read'])->requestedTools));
    }

    public function test_incomplete_provider_output_is_rejected_and_refunded(): void
    {
        $tenant = $this->tenant();
        $provider = new FakeAiProvider;
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'partial'],
            model: 'fake-1',
            inputTokens: 1,
            outputTokens: 1,
            costUsd: 0.01,
            finishReason: 'length',
        ));

        $this->expectException(AiMalformedResponseException::class);
        try {
            $this->gateway($provider)->execute($this->request($tenant));
        } finally {
            self::assertSame('refunded', DB::table('ai_credit_reservations')->value('status'));
        }
    }

    private function gateway(FakeAiProvider $provider): ResilientAiGateway
    {
        return new ResilientAiGateway(
            new ConfiguredAiProviderPool([
                'fake_structured_text' => ['fake.primary' => $provider],
            ]),
            new LaravelStructuredOutputValidator,
            new AiCapabilityPolicy,
            new BudgetEnforcer,
            new AiExecutionStore,
        );
    }

    /**
     * @param  list<string>  $requestedTools
     * @param  list<string>|null  $allowedTools
     */
    private function request(
        Tenant $tenant,
        bool $authorized = true,
        bool $entitled = true,
        bool $allowlisted = true,
        string $audience = 'private',
        string $provider = 'fake',
        string $userInput = 'What did I sell today?',
        array $requestedTools = [],
        ?array $allowedTools = null,
        ?string $correlationId = null,
        string $secretReference = 'fake.primary',
    ): AiExecutionRequest {
        config()->set('ai.private.tenant_allowlist', $allowlisted ? [(string) $tenant->getKey()] : []);

        return new AiExecutionRequest(
            context: new AiExecutionContext(
                correlationId: $correlationId ?? (string) Str::uuid(),
                audience: $audience,
                capability: 'private_tenant_agent',
                tenantId: (string) $tenant->getKey(),
                billingTenantId: (string) $tenant->getKey(),
                actorId: (string) $tenant->user_id,
                authorized: $authorized,
                entitled: $entitled,
                rateLimitSubject: hash('sha256', (string) $tenant->getKey().'|actor'),
                allowedTools: $allowedTools ?? ['tenant_catalog.read', 'tenant_lead.prepare'],
            ),
            selection: $this->selection(
                $tenant,
                providerKey: $provider,
                secretReference: $secretReference,
            ),
            operation: 'private_agent.answer',
            userInput: $userInput,
            requestedTools: $requestedTools,
        );
    }

    private function selection(
        Tenant $tenant,
        string $providerKey = 'fake',
        string $secretReference = 'fake.primary',
    ): AiRuntimeSelection {
        $providerId = (string) Str::uuid();
        $providerVersionId = (string) Str::uuid();
        $modelId = (string) Str::uuid();
        $modelVersionId = (string) Str::uuid();
        $promptDefinitionId = (string) Str::uuid();
        $promptVersionId = (string) Str::uuid();
        $agentDefinitionId = (string) Str::uuid();
        $agentVersionId = (string) Str::uuid();
        $releaseDecisionId = (string) Str::uuid();
        $now = now();

        $existingProvider = DB::table('ai_providers')->where('provider_key', $providerKey)->first();
        if ($existingProvider === null) {
            DB::table('ai_providers')->insert([
                'id' => $providerId,
                'provider_key' => $providerKey,
                'adapter_key' => 'fake_structured_text',
                'default_secret_reference' => 'fake.primary',
                'name' => 'Fake',
                'status' => 'active',
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('ai_provider_versions')->insert([
                'id' => $providerVersionId,
                'provider_id' => $providerId,
                'version' => 1,
                'checksum' => hash('sha256', $providerVersionId),
                'secret_reference' => 'fake.primary',
                'status' => 'active',
                'configuration' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
                'released_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('ai_models')->insert([
                'id' => $modelId,
                'provider_id' => $providerId,
                'key' => 'fake-1',
                'name' => 'Fake 1',
                'status' => 'active',
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('ai_model_versions')->insert([
                'id' => $modelVersionId,
                'model_id' => $modelId,
                'provider_version_id' => $providerVersionId,
                'version' => 1,
                'checksum' => hash('sha256', $modelVersionId),
                'context_window' => 800,
                'input_cost_minor' => 0,
                'output_cost_minor' => 25_000,
                'currency' => 'USD',
                'configuration' => json_encode(['capabilities' => ['structured_output']], JSON_THROW_ON_ERROR),
                'released_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $providerId = (string) $existingProvider->id;
            $providerVersionId = (string) DB::table('ai_provider_versions')
                ->where('provider_id', $providerId)
                ->value('id');
            $modelId = (string) DB::table('ai_models')
                ->where('provider_id', $providerId)
                ->where('key', 'fake-1')
                ->value('id');
            $modelVersionId = (string) DB::table('ai_model_versions')
                ->where('model_id', $modelId)
                ->value('id');
        }
        DB::table('ai_prompt_definitions')->insert([
            'id' => $promptDefinitionId,
            'tenant_id' => (string) $tenant->id,
            'key' => 'gateway.prompt.'.Str::uuid(),
            'name' => 'Gateway prompt',
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_prompt_versions')->insert([
            'id' => $promptVersionId,
            'tenant_id' => (string) $tenant->id,
            'prompt_definition_id' => $promptDefinitionId,
            'version' => 1,
            'checksum' => hash('sha256', $promptVersionId),
            'template_ciphertext' => 'fixture-ciphertext',
            'template_hash' => hash('sha256', 'Answer only from authorized evidence.'),
            'encryption_key_version' => 'v1',
            'input_schema' => json_encode([], JSON_THROW_ON_ERROR),
            'released_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_agent_definitions')->insert([
            'id' => $agentDefinitionId,
            'tenant_id' => (string) $tenant->id,
            'key' => 'gateway.agent.'.Str::uuid(),
            'name' => 'Gateway agent',
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_agent_versions')->insert([
            'id' => $agentVersionId,
            'tenant_id' => (string) $tenant->id,
            'agent_definition_id' => $agentDefinitionId,
            'model_version_id' => $modelVersionId,
            'version' => 1,
            'checksum' => hash('sha256', $agentVersionId),
            'configuration' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'released_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new AiRuntimeSelection(
            providerVersionId: $providerVersionId,
            modelVersionId: $modelVersionId,
            agentVersionId: $agentVersionId,
            promptVersionId: $promptVersionId,
            providerKey: $providerKey,
            adapterKey: 'fake_structured_text',
            secretReference: $secretReference,
            modelKey: 'fake-1',
            systemPrompt: 'Answer only from authorized evidence.',
            outputSchema: 'private_agent.answer.v1',
            requiredCapabilities: ['structured_output'],
            maxOutputTokens: 800,
            releaseDecisionId: $releaseDecisionId,
        );
    }

    /** @param array<string, mixed> $data */
    private function providerResult(array $data = ['answer' => 'Datos verificados.'], float $cost = 0.10): AiProviderResult
    {
        return new AiProviderResult(
            data: $data,
            model: 'fake-1',
            inputTokens: 12,
            outputTokens: 8,
            costUsd: $cost,
        );
    }

    private function tenant(float $balance = 1.0): Tenant
    {
        return Tenant::factory()->create([
            'ai_balance' => $balance,
            'ai_reserved_balance' => 0,
        ]);
    }

    private function expectDenied(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Expected AI denial: {$reason}");
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame($reason, $exception->reason);
        }
    }
}
