<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProductionOpsCommandsTest extends TestCase
{
    public function test_production_config_guard_blocks_debug_and_sync_queue(): void
    {
        config()->set('app.debug', true);
        config()->set('queue.default', 'sync');
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'array');
        config()->set('services.payment.provider', 'generic');

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertSame('failed', $payload['status']);
        self::assertContains('APP_DEBUG', array_column($payload['violations'], 'key'));
        self::assertContains('QUEUE_CONNECTION', array_column($payload['violations'], 'key'));
        self::assertContains('SESSION_DRIVER', array_column($payload['violations'], 'key'));
    }

    public function test_production_config_guard_rejects_insecure_transport_and_session_configuration(): void
    {
        config()->set([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'http://conecta.example',
            'queue.default' => 'database',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => 'none',
            'security.hsts.enabled' => false,
            'security.admin_mfa_required' => false,
            'services.payment.provider' => 'generic',
            'ai.enabled' => false,
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertContains('APP_URL', $keys);
        self::assertContains('SESSION_SECURE_COOKIE', $keys);
        self::assertContains('SESSION_HTTP_ONLY', $keys);
        self::assertContains('SESSION_SAME_SITE', $keys);
        self::assertContains('SECURITY_HSTS_ENABLED', $keys);
        self::assertContains('SECURITY_ADMIN_MFA_REQUIRED', $keys);
    }

    public function test_production_config_guard_rejects_legacy_social_login(): void
    {
        config()->set([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://conecta.example',
            'queue.default' => 'database',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'security.hsts.enabled' => true,
            'security.admin_mfa_required' => true,
            'services.payment.provider' => 'generic',
            'auth.social_login_enabled' => true,
            'ai.enabled' => false,
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertContains(
            'SOCIAL_LOGIN_ENABLED',
            array_column($payload['violations'], 'key'),
        );
    }

    public function test_production_config_guard_requires_privacy_safe_observability(): void
    {
        config()->set([
            'sentry.dsn' => null,
            'sentry.send_default_pii' => true,
            'observability.opentelemetry.enabled' => false,
            'observability.opentelemetry.endpoint' => 'http://collector.internal',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertContains('SENTRY_LARAVEL_DSN', $keys);
        self::assertContains('SENTRY_SEND_DEFAULT_PII', $keys);
        self::assertContains('OTEL_PHP_AUTOLOAD_ENABLED', $keys);
        self::assertContains('OTEL_EXPORTER_OTLP_ENDPOINT', $keys);
    }

    public function test_production_config_guard_rejects_unbudgeted_or_uncredentialed_ai(): void
    {
        config()->set([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://conecta.example',
            'queue.default' => 'database',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'ai.enabled' => true,
            'ai.allowed_environments' => ['production'],
            'ai.providers.openai.enabled' => true,
            'ai.providers.openai.models.gpt-4o-mini.input_usd_per_million' => 0,
            'ai.providers.openai.models.gpt-4o-mini.output_usd_per_million' => null,
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'openai',
            'ai.capabilities.public_discovery.max_cost_usd' => 0,
            'ai.capabilities.public_discovery.requests_per_minute' => 0,
            'ai.public.billing_tenant_id' => null,
            'services.openai.api_key' => null,
            'services.openai.chat_completions_endpoint' => null,
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertContains('AI_public_discovery_MAX_COST', $keys);
        self::assertContains('AI_public_discovery_RATE_LIMIT', $keys);
        self::assertContains('OPENAI_API_KEY', $keys);
        self::assertContains('OPENAI_CHAT_COMPLETIONS_ENDPOINT', $keys);
        self::assertContains('OPENAI_MODEL_PRICING_gpt-4o-mini', $keys);
        self::assertContains('AI_PUBLIC_BILLING_TENANT_ID', $keys);
    }

    public function test_production_config_guard_rejects_blank_model_identifiers(): void
    {
        config()->set([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://conecta.example',
            'queue.default' => 'database',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'ai.enabled' => true,
            'ai.allowed_environments' => ['production'],
            'ai.providers.openai.enabled' => true,
            'ai.capabilities.public_discovery.enabled' => true,
            'ai.capabilities.public_discovery.provider' => 'openai',
            'ai.capabilities.public_discovery.models' => [''],
            'ai.capabilities.public_discovery.max_cost_usd' => 0.02,
            'ai.capabilities.public_discovery.requests_per_minute' => 10,
            'ai.public.billing_tenant_id' => '019c1234-5678-7000-8000-000000000001',
            'services.openai.api_key' => 'sk-production-test',
            'services.openai.chat_completions_endpoint' => 'https://api.openai.com/v1/chat/completions',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertContains(
            'AI_public_discovery_MODELS',
            array_column($payload['violations'], 'key'),
        );
    }

    public function test_production_config_guard_rejects_invalid_private_ai_tenant_allowlist(): void
    {
        config()->set([
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://conecta.example',
            'queue.default' => 'database',
            'cache.default' => 'redis',
            'session.driver' => 'database',
            'ai.enabled' => true,
            'ai.allowed_environments' => ['production'],
            'ai.capabilities.private_tenant_agent.enabled' => true,
            'ai.capabilities.private_tenant_agent.provider' => 'fake',
            'ai.capabilities.private_tenant_agent.models' => ['deterministic-v1'],
            'ai.capabilities.private_tenant_agent.max_cost_usd' => 0.02,
            'ai.capabilities.private_tenant_agent.requests_per_minute' => 10,
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => ['negocio-slug', 'negocio-slug'],
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertContains(
            'AI_PRIVATE_TENANT_ALLOWLIST',
            array_column($payload['violations'], 'key'),
        );
    }

    public function test_production_config_guard_rejects_unsafe_tool_confirmation_switches(): void
    {
        config()->set([
            'ai.enabled' => false,
            'ai.private.enabled' => false,
            'ai.tool_confirmations.enabled' => true,
            'ai.tool_confirmations.ttl_seconds' => 7200,
            'ai.tool_confirmations.requests_per_minute' => 0,
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertContains('AI_TOOL_CONFIRMATIONS_ENABLED', $keys);
        self::assertContains('AI_TOOL_CONFIRMATION_TTL_SECONDS', $keys);
        self::assertContains('AI_TOOL_CONFIRMATION_REQUESTS_PER_MINUTE', $keys);
    }

    public function test_storage_smoke_can_round_trip_a_file_on_selected_disk(): void
    {
        Storage::fake('smoke');

        $exitCode = Artisan::call('storage:smoke', [
            '--disk' => 'smoke',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('ok', $payload['status']);
        self::assertSame('smoke', $payload['disk']);
        self::assertSame(['put' => true, 'read' => true, 'delete' => true], $payload['steps']);
    }

    public function test_observability_smoke_emits_structured_log_without_sentry_requirement(): void
    {
        $exitCode = Artisan::call('observability:smoke', [
            '--logs' => true,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('ok', $payload['status']);
        self::assertTrue($payload['checks']['structured_log']['ok']);
    }
}
