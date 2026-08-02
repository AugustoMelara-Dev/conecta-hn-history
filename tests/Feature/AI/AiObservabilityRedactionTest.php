<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\DTOs\AiExecutionContext;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Application\Ai\Policies\AiCapabilityPolicy;
use App\Exceptions\Ai\AiExecutionFailedException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\AiExecutionStore;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Infrastructure\AI\LaravelStructuredOutputValidator;
use App\Infrastructure\AI\ResilientAiGateway;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiRuntimeSelectionFixture;
use Tests\TestCase;

final class AiObservabilityRedactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'ai.enabled' => true,
            'ai.allowed_environments' => ['testing'],
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [],
            'ai.providers.fake.enabled' => true,
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.capabilities.private_tenant_agent.audience' => 'private',
            'ai.capabilities.private_tenant_agent.billing_mode' => 'tenant',
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
            'ai.capabilities.private_tenant_agent.models' => ['fake-1'],
            'ai.capabilities.private_tenant_agent.requires_tenant' => true,
            'ai.capabilities.private_tenant_agent.max_cost_usd' => 0.20,
            'ai.capabilities.private_tenant_agent.requests_per_minute' => 50,
            'ai.schemas.private_agent.answer.v1.rules' => [
                'answer' => ['required', 'string', 'max:500'],
            ],
            'ai.commercial_credits.credits_per_provider_usd' => 1.0,
            'ai.commercial_credits.minimum_charge' => 0.01,
        ]);
    }

    public function test_provider_failure_logs_keep_correlation_id_without_prompt_secret_or_provider_echo(): void
    {
        $tenant = Tenant::factory()->create(['ai_balance' => 1.0, 'ai_reserved_balance' => 0]);
        config()->set('ai.private.tenant_allowlist', [(string) $tenant->id]);
        $selection = AiRuntimeSelectionFixture::create(
            tenantId: (string) $tenant->id,
            providerKey: 'fake',
            adapterKey: 'fake_structured_text',
            modelKey: 'fake-1',
            systemPrompt: 'SYSTEM SECRET: root prompt',
        );

        Log::spy();

        $provider = new FakeAiProvider('fake');
        $provider->queueFailure(new AiProviderUnavailableException('SECRET provider echo'));

        try {
            $this->gateway($provider)->execute($this->request($tenant, $selection, 'customer password is swordfish'));
            self::fail('Expected a provider failure.');
        } catch (AiProviderUnavailableException $exception) {
            self::assertSame('AI provider is temporarily unavailable.', $exception->getMessage());
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($selection): bool {
                $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

                return $message === 'AI execution failed.'
                    && ($context['correlation_id'] ?? null) !== null
                    && ($context['provider'] ?? null) === $selection->providerKey
                    && ($context['capability'] ?? null) === 'private_tenant_agent'
                    && ($context['exception'] ?? null) === AiProviderUnavailableException::class
                    && ! str_contains($serialized, 'swordfish')
                    && ! str_contains($serialized, 'SYSTEM SECRET')
                    && ! str_contains($serialized, 'SECRET provider echo')
                    && ! str_contains($serialized, $selection->secretReference);
            });
    }

    public function test_resource_overrun_logs_keep_safe_failure_code_and_usage_without_payload_or_credentials(): void
    {
        $tenant = Tenant::factory()->create(['ai_balance' => 1.0, 'ai_reserved_balance' => 0]);
        config()->set('ai.private.tenant_allowlist', [(string) $tenant->id]);
        $selection = AiRuntimeSelectionFixture::create(
            tenantId: (string) $tenant->id,
            providerKey: 'fake',
            adapterKey: 'fake_structured_text',
            modelKey: 'fake-1',
            systemPrompt: 'SYSTEM SECRET: do not leak',
        );

        Log::spy();

        $provider = new FakeAiProvider('fake');
        $provider->queueResult(new AiProviderResult(
            data: ['answer' => 'unsafe payload'],
            model: 'fake-1',
            inputTokens: 4,
            outputTokens: 900,
            costUsd: 0.10,
        ));

        try {
            $this->gateway($provider)->execute($this->request($tenant, $selection, 'bearer sk-live-super-secret'));
            self::fail('Expected a resource overrun failure.');
        } catch (AiExecutionFailedException $exception) {
            self::assertSame('AI provider exceeded authorized resource limits.', $exception->getMessage());
        }

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(function (string $message, array $context) use ($selection): bool {
                $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

                return $message === 'AI provider exceeded an authorized resource policy.'
                    && ($context['correlation_id'] ?? null) !== null
                    && ($context['reason'] ?? null) === 'output_token_overrun'
                    && ($context['provider'] ?? null) === $selection->providerKey
                    && ($context['model'] ?? null) === $selection->modelKey
                    && ($context['output_tokens'] ?? null) === 900
                    && ($context['provider_cost_usd'] ?? null) === 0.10
                    && ! str_contains($serialized, 'unsafe payload')
                    && ! str_contains($serialized, 'SYSTEM SECRET')
                    && ! str_contains($serialized, 'sk-live-super-secret')
                    && ! str_contains($serialized, $selection->secretReference);
            });
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

    private function request(Tenant $tenant, object $selection, string $userInput): AiExecutionRequest
    {
        return new AiExecutionRequest(
            context: new AiExecutionContext(
                correlationId: (string) Str::uuid(),
                audience: 'private',
                capability: 'private_tenant_agent',
                tenantId: (string) $tenant->id,
                billingTenantId: (string) $tenant->id,
                actorId: (string) $tenant->user_id,
                authorized: true,
                entitled: true,
                rateLimitSubject: hash('sha256', (string) $tenant->id.'|owner'),
                allowedTools: ['tenant_catalog.read'],
            ),
            selection: $selection,
            operation: 'private_agent.answer',
            userInput: $userInput,
        );
    }
}
