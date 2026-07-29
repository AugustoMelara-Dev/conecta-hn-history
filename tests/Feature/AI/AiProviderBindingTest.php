<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\OpenAiStructuredTextProvider;
use Tests\TestCase;

final class AiProviderBindingTest extends TestCase
{
    public function test_pool_fails_closed_when_the_adapter_secret_reference_is_not_deployable(): void
    {
        config()->set('ai.providers.openai.enabled', false);
        config()->set('services.openai.api_key', '');
        $this->app->forgetInstance(AiProviderPool::class);

        $this->expectException(AiExecutionDeniedException::class);
        $this->app->make(AiProviderPool::class)->resolve('openai_structured_text');
    }

    public function test_explicit_openai_enablement_selects_the_structured_adapter(): void
    {
        config()->set('ai.providers.openai.enabled', true);
        config()->set('ai.registry.openai_secret_reference', 'openai.primary');
        config()->set('services.openai.api_key', 'deployment-secret-value');
        $this->app->forgetInstance(AiProviderPool::class);

        $provider = $this->app->make(AiProviderPool::class)->resolve('openai_structured_text');

        self::assertInstanceOf(OpenAiStructuredTextProvider::class, $provider);
        self::assertSame('openai', $provider->name());
    }

    public function test_unknown_adapter_fails_closed_without_echoing_the_key(): void
    {
        config()->set('ai.providers.openai.enabled', true);
        config()->set('ai.registry.openai_secret_reference', 'openai.primary');
        config()->set('services.openai.api_key', 'deployment-secret-value');
        $this->app->forgetInstance(AiProviderPool::class);

        try {
            $this->app->make(AiProviderPool::class)->resolve('secret-looking-unknown-adapter');
            self::fail('Unknown adapters must fail closed.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('provider_adapter', $exception->reason);
            self::assertStringNotContainsString('secret-looking', $exception->getMessage());
        }
    }
}
