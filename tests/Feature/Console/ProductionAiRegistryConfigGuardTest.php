<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionAiRegistryConfigGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_rejects_unsafe_ai_configuration_without_echoing_secret_values(): void
    {
        $this->baselineConfig();
        config()->set([
            'ai.enabled' => true,
            'ai.registry.bootstrap_enabled' => true,
            'ai.public.billing_tenant_id' => 'definitely-not-a-uuid',
            'ai.providers.openai.enabled' => true,
            'ai.providers.openai.secret_reference' => 'sk-live-top-secret',
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'openai',
            'ai.capabilities.public_discovery.models' => ['gpt-4o-mini'],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 20,
            'ai.retention.conversation_days' => 0,
            'ai.execution_retention.encryption_key_version' => '',
            'services.openai.api_key' => 'sk-live-top-secret',
            'services.openai.chat_completions_endpoint' => 'https://api.openai.test/v1/chat/completions',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'staging',
            '--json' => true,
        ]);

        self::assertSame(1, $exitCode);
        $output = Artisan::output();
        self::assertStringContainsString('"AI_REGISTRY_BOOTSTRAP_ENABLED"', $output);
        self::assertStringContainsString('"AI_PUBLIC_BILLING_TENANT_ID"', $output);
        self::assertStringContainsString('"AI_OPENAI_SECRET_REFERENCE"', $output);
        self::assertStringContainsString('"AI_CONVERSATION_RETENTION_DAYS"', $output);
        self::assertStringContainsString('"AI_ENCRYPTION_KEY_VERSION"', $output);
        self::assertStringNotContainsString('sk-live-top-secret', $output);
        self::assertStringNotContainsString('definitely-not-a-uuid', $output);
    }

    public function test_guard_accepts_governed_ai_configuration_when_registry_and_owner_are_explicit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->baselineConfig();
        config()->set([
            'ai.enabled' => true,
            'ai.registry.bootstrap_enabled' => false,
            'ai.public.billing_tenant_id' => (string) $tenant->id,
            'ai.providers.openai.enabled' => true,
            'ai.providers.openai.secret_reference' => 'openai.primary',
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'openai',
            'ai.capabilities.public_discovery.models' => ['gpt-4o-mini'],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 20,
            'ai.retention.conversation_days' => 30,
            'ai.execution_retention.encryption_key_version' => 'v1',
            'services.openai.api_key' => 'test-key-only-for-config-shape',
            'services.openai.chat_completions_endpoint' => 'https://api.openai.test/v1/chat/completions',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'staging',
            '--json' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('"status": "ok"', Artisan::output());
    }

    private function baselineConfig(): void
    {
        config()->set([
            'app.debug' => false,
            'queue.default' => 'redis',
            'database.redis.default.scheme' => 'tls',
            'database.redis.default.url' => 'rediss://cache.example.test',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'services.payment.provider' => 'generic',
            'app.key' => 'base64:test-key',
            'app.url' => 'https://conecta.example.test',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'security.hsts.enabled' => true,
            'security.admin_mfa_required' => true,
            'sentry.dsn' => 'https://public@example.com/1',
            'sentry.send_default_pii' => false,
            'observability.opentelemetry.enabled' => true,
            'observability.opentelemetry.endpoint' => 'https://otel.example.test/v1/traces',
            'ai.allowed_environments' => ['staging', 'production'],
            'ai.commercial_credits.pricing_version' => 'credits-v1',
            'ai.private.enabled' => false,
            'ai.tool_confirmations.enabled' => false,
            'ai.knowledge.enabled' => false,
            'ai.providers.openai.models.gpt-4o-mini.input_usd_per_million' => 0.15,
            'ai.providers.openai.models.gpt-4o-mini.output_usd_per_million' => 0.60,
        ]);
    }
}
