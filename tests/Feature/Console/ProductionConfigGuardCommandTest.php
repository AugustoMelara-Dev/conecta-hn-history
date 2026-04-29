<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionConfigGuardCommandTest extends TestCase
{
    public function test_it_fails_production_when_runtime_configuration_is_unsafe(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.key' => '',
            'queue.default' => 'sync',
            'cache.default' => 'file',
            'observability.error_tracking.provider' => 'datadog',
            'observability.error_tracking.sentry_dsn' => '',
            'observability.error_tracking.frontend_dsn' => '',
            'observability.logs.provider' => 'datadog',
            'observability.logs.better_stack_source_token' => '',
            'observability.logs.better_stack_ingesting_host' => '',
            'services.payment.provider' => 'stripe',
            'services.payment.webhook_secret' => 'whsec_dummy_secret_for_local_dev_only',
            'services.stripe.key' => '',
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
            'services.stripe.use_price_ids' => true,
            'services.stripe.prices.pro' => '',
            'mail.default' => 'log',
            'mail.from.address' => '',
            'services.resend.key' => '',
            'services.resend.webhook_secret' => '',
            'observability.channels.whatsapp' => 'twilio',
            'observability.channels.sms' => 'twilio',
            'services.whatsapp_cloud.access_token' => '',
            'services.whatsapp_cloud.phone_number_id' => '',
            'services.whatsapp_cloud.verify_token' => '',
            'services.whatsapp_cloud.app_secret' => '',
            'services.openai.api_key' => '',
            'ai-rag.private.vector_store.driver' => 'qdrant',
            'ai-rag.private.qdrant.enabled' => true,
            'ai-rag.private.qdrant.url' => '',
            'ai-rag.private.qdrant.api_key' => '',
            'ai-rag.scopes.tenant_private.collection' => '',
            'webpush.vapid.public_key' => 'placeholder',
            'webpush.vapid.private_key' => '',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $violationKeys = array_column($payload['violations'], 'key');

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertContains('APP_DEBUG', $violationKeys);
        $this->assertContains('QUEUE_CONNECTION', $violationKeys);
        $this->assertContains('CACHE_STORE', $violationKeys);
        $this->assertContains('ERROR_TRACKING_PROVIDER', $violationKeys);
        $this->assertContains('SENTRY_LARAVEL_DSN', $violationKeys);
        $this->assertContains('VITE_SENTRY_DSN', $violationKeys);
        $this->assertContains('LOG_AGGREGATION_PROVIDER', $violationKeys);
        $this->assertContains('BETTER_STACK_SOURCE_TOKEN', $violationKeys);
        $this->assertContains('BETTER_STACK_INGESTING_HOST', $violationKeys);
        $this->assertContains('PAYMENT_WEBHOOK_SECRET', $violationKeys);
        $this->assertContains('STRIPE_KEY', $violationKeys);
        $this->assertContains('STRIPE_SECRET', $violationKeys);
        $this->assertContains('STRIPE_WEBHOOK_SECRET', $violationKeys);
        $this->assertContains('STRIPE_PRICE_PRO', $violationKeys);
        $this->assertContains('MAIL_MAILER', $violationKeys);
        $this->assertContains('MAIL_FROM_ADDRESS', $violationKeys);
        $this->assertContains('RESEND_API_KEY', $violationKeys);
        $this->assertContains('RESEND_WEBHOOK_SECRET', $violationKeys);
        $this->assertContains('CUSTOMER_AUTOMATION_WHATSAPP_PROVIDER', $violationKeys);
        $this->assertContains('SMS_MARKETING_PROVIDER', $violationKeys);
        $this->assertContains('OPENAI_API_KEY', $violationKeys);
        $this->assertContains('TENANT_PRIVATE_QDRANT_URL', $violationKeys);
        $this->assertContains('VAPID_PRIVATE_KEY', $violationKeys);
    }

    public function test_it_passes_production_with_rotated_async_runtime_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'observability.error_tracking.provider' => 'sentry',
            'observability.error_tracking.sentry_dsn' => 'https://public@o123456.ingest.sentry.io/123',
            'observability.error_tracking.frontend_dsn' => 'https://public@o123456.ingest.sentry.io/456',
            'observability.logs.provider' => 'better_stack',
            'observability.logs.better_stack_source_token' => 'better_stack_rotated_source_token',
            'observability.logs.better_stack_ingesting_host' => 'in.logs.betterstack.com',
            'services.payment.provider' => 'generic',
            'services.payment.webhook_secret' => 'whsec_rotated_realistic_payment_secret',
            'services.stripe.key' => '',
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
            'services.stripe.prices.pro' => '',
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@mail.vitobusinessos.test',
            'services.resend.key' => 're_rotated_realistic_resend_secret',
            'services.resend.webhook_secret' => 'resend_rotated_realistic_webhook_secret',
            'observability.channels.whatsapp' => 'whatsapp_cloud',
            'observability.channels.sms' => 'missing',
            'services.whatsapp_cloud.access_token' => 'EAA_rotated_whatsapp_access_token',
            'services.whatsapp_cloud.phone_number_id' => '1234567890',
            'services.whatsapp_cloud.verify_token' => 'rotated_whatsapp_verify_token',
            'services.whatsapp_cloud.app_secret' => 'rotated_whatsapp_app_secret',
            'services.openai.api_key' => 'sk-proj-rotated-realistic-openai-secret',
            'ai-rag.private.vector_store.driver' => 'qdrant',
            'ai-rag.private.qdrant.enabled' => true,
            'ai-rag.private.qdrant.url' => 'https://qdrant.vito-business-os.internal:6333',
            'ai-rag.private.qdrant.api_key' => 'qdrant_rotated_realistic_secret',
            'ai-rag.scopes.tenant_private.collection' => 'tenant_private_documents',
            'webpush.vapid.public_key' => 'BNrotatedRealisticPublicKey',
            'webpush.vapid.private_key' => 'rotatedRealisticPrivateKey',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame([], $payload['violations']);
        $this->assertContains('PAYMENT_PROVIDER', $payload['checked']);
    }

    public function test_it_fails_production_when_single_database_redis_uses_separate_cache_db(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'cache.stores.redis.connection' => 'cache',
            'database.redis.default.url' => 'redis://redis-15695.c16.us-east-1-2.ec2.cloud.redislabs.com:15695',
            'database.redis.default.database' => 0,
            'database.redis.cache.url' => 'redis://redis-15695.c16.us-east-1-2.ec2.cloud.redislabs.com:15695',
            'database.redis.cache.database' => 1,
            'observability.error_tracking.provider' => 'sentry',
            'observability.error_tracking.sentry_dsn' => 'https://public@o123456.ingest.sentry.io/123',
            'observability.error_tracking.frontend_dsn' => 'https://public@o123456.ingest.sentry.io/456',
            'observability.logs.provider' => 'better_stack',
            'observability.logs.better_stack_source_token' => 'better_stack_rotated_source_token',
            'observability.logs.better_stack_ingesting_host' => 'in.logs.betterstack.com',
            'services.payment.provider' => 'generic',
            'services.payment.webhook_secret' => 'whsec_rotated_realistic_payment_secret',
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@mail.vitobusinessos.test',
            'services.resend.key' => 're_rotated_realistic_resend_secret',
            'services.resend.webhook_secret' => 'resend_rotated_realistic_webhook_secret',
            'observability.channels.whatsapp' => 'missing',
            'observability.channels.sms' => 'missing',
            'services.openai.api_key' => 'sk-proj-rotated-realistic-openai-secret',
            'ai-rag.private.vector_store.driver' => 'qdrant',
            'ai-rag.private.qdrant.enabled' => true,
            'ai-rag.private.qdrant.url' => 'https://qdrant.vito-business-os.internal:6333',
            'ai-rag.private.qdrant.api_key' => 'qdrant_rotated_realistic_secret',
            'ai-rag.scopes.tenant_private.collection' => 'tenant_private_documents',
            'webpush.vapid.public_key' => 'BNrotatedRealisticPublicKey',
            'webpush.vapid.private_key' => 'rotatedRealisticPrivateKey',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $violationKeys = array_column($payload['violations'], 'key');

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertContains('REDIS_CACHE_DB', $violationKeys);
    }

    public function test_it_allows_local_development_defaults(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'queue.default' => 'sync',
            'cache.default' => 'file',
            'services.payment.provider' => 'generic',
            'services.payment.webhook_secret' => 'whsec_dummy_secret_for_local_dev_only',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'local',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
    }

    public function test_it_keeps_stripe_secrets_optional_while_generic_billing_is_active(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'observability.error_tracking.provider' => 'sentry',
            'observability.error_tracking.sentry_dsn' => 'https://public@o123456.ingest.sentry.io/123',
            'observability.error_tracking.frontend_dsn' => 'https://public@o123456.ingest.sentry.io/456',
            'observability.logs.provider' => 'better_stack',
            'observability.logs.better_stack_source_token' => 'better_stack_rotated_source_token',
            'observability.logs.better_stack_ingesting_host' => 'in.logs.betterstack.com',
            'services.payment.provider' => 'generic',
            'services.payment.webhook_secret' => 'whsec_rotated_realistic_payment_secret',
            'services.stripe.key' => '',
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
            'services.stripe.use_price_ids' => true,
            'services.stripe.prices.pro' => '',
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@mail.vitobusinessos.test',
            'services.resend.key' => 're_rotated_realistic_resend_secret',
            'services.resend.webhook_secret' => 'resend_rotated_realistic_webhook_secret',
            'observability.channels.whatsapp' => 'whatsapp_cloud',
            'observability.channels.sms' => 'missing',
            'services.whatsapp_cloud.access_token' => 'EAA_rotated_whatsapp_access_token',
            'services.whatsapp_cloud.phone_number_id' => '1234567890',
            'services.whatsapp_cloud.verify_token' => 'rotated_whatsapp_verify_token',
            'services.whatsapp_cloud.app_secret' => 'rotated_whatsapp_app_secret',
            'services.openai.api_key' => 'sk-proj-rotated-realistic-openai-secret',
            'ai-rag.private.vector_store.driver' => 'qdrant',
            'ai-rag.private.qdrant.enabled' => true,
            'ai-rag.private.qdrant.url' => 'https://qdrant.vito-business-os.internal:6333',
            'ai-rag.private.qdrant.api_key' => 'qdrant_rotated_realistic_secret',
            'ai-rag.scopes.tenant_private.collection' => 'tenant_private_documents',
            'webpush.vapid.public_key' => 'BNrotatedRealisticPublicKey',
            'webpush.vapid.private_key' => 'rotatedRealisticPrivateKey',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertNotContains('STRIPE_SECRET', array_column($payload['violations'], 'key'));
    }

    public function test_it_allows_whatsapp_automation_to_be_disabled_in_production(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'observability.error_tracking.provider' => 'sentry',
            'observability.error_tracking.sentry_dsn' => 'https://public@o123456.ingest.sentry.io/123',
            'observability.error_tracking.frontend_dsn' => 'https://public@o123456.ingest.sentry.io/456',
            'observability.logs.provider' => 'better_stack',
            'observability.logs.better_stack_source_token' => 'better_stack_rotated_source_token',
            'observability.logs.better_stack_ingesting_host' => 'in.logs.betterstack.com',
            'services.payment.provider' => 'generic',
            'services.payment.webhook_secret' => 'whsec_rotated_realistic_payment_secret',
            'mail.default' => 'resend',
            'mail.from.address' => 'no-reply@mail.vitobusinessos.test',
            'services.resend.key' => 're_rotated_realistic_resend_secret',
            'services.resend.webhook_secret' => 'resend_rotated_realistic_webhook_secret',
            'observability.channels.whatsapp' => 'missing',
            'observability.channels.sms' => 'missing',
            'services.whatsapp_cloud.access_token' => '',
            'services.whatsapp_cloud.phone_number_id' => '',
            'services.whatsapp_cloud.verify_token' => '',
            'services.whatsapp_cloud.app_secret' => '',
            'services.openai.api_key' => 'sk-proj-rotated-realistic-openai-secret',
            'ai-rag.private.vector_store.driver' => 'qdrant',
            'ai-rag.private.qdrant.enabled' => true,
            'ai-rag.private.qdrant.url' => 'https://qdrant.vito-business-os.internal:6333',
            'ai-rag.private.qdrant.api_key' => 'qdrant_rotated_realistic_secret',
            'ai-rag.scopes.tenant_private.collection' => 'tenant_private_documents',
            'webpush.vapid.public_key' => 'BNrotatedRealisticPublicKey',
            'webpush.vapid.private_key' => 'rotatedRealisticPrivateKey',
        ]);

        $exitCode = Artisan::call('config:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $violationKeys = array_column($payload['violations'], 'key');

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertNotContains('WHATSAPP_CLOUD_ACCESS_TOKEN', $violationKeys);
        $this->assertNotContains('WHATSAPP_CLOUD_APP_SECRET', $violationKeys);
    }
}
