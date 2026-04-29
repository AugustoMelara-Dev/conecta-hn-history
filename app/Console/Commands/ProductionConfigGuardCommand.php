<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ProductionConfigGuardCommand extends Command
{
    protected $signature = 'config:production-guard
        {--env= : Environment to validate, defaults to APP_ENV}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Fail release if staging/production runtime configuration is unsafe.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $appEnvironment = (string) config('app.env', app()->environment());
        $isReleaseEnvironment = in_array($environment, ['production', 'staging'], true);
        $violations = [];
        $checked = [
            'APP_ENV',
            'APP_DEBUG',
            'APP_KEY',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
            'ERROR_TRACKING_PROVIDER',
            'LOG_AGGREGATION_PROVIDER',
            'PAYMENT_PROVIDER',
            'PAYMENT_WEBHOOK_SECRET',
            'MAIL_MAILER',
            'CUSTOMER_AUTOMATION_WHATSAPP_PROVIDER',
            'SMS_MARKETING_PROVIDER',
            'TENANT_PRIVATE_QDRANT_ENABLED',
        ];

        if ($isReleaseEnvironment && $appEnvironment !== $environment) {
            $violations[] = $this->violation(
                'APP_ENV',
                $appEnvironment,
                $environment,
                'Release gates must run against the same environment they validate.'
            );
        }

        if ($isReleaseEnvironment && (bool) config('app.debug', false)) {
            $violations[] = $this->violation(
                'APP_DEBUG',
                'true',
                'false',
                'Debug mode can expose stack traces, env values, and request context.'
            );
        }

        if ($isReleaseEnvironment && $this->isBlankOrPlaceholder((string) config('app.key', ''))) {
            $violations[] = $this->violation(
                'APP_KEY',
                $this->redact((string) config('app.key', '')),
                'configured rotated key',
                'Laravel encryption and signed payloads require a real runtime key.'
            );
        }

        $queueConnection = (string) config('queue.default', 'sync');
        if ($isReleaseEnvironment && $queueConnection !== 'redis') {
            $violations[] = $this->violation(
                'QUEUE_CONNECTION',
                $queueConnection,
                'redis',
                'Staging/production jobs, locks, and outbox processing must use managed Redis instead of inline or database-backed queues.'
            );
        }

        $cacheStore = (string) config('cache.default', 'file');
        if ($isReleaseEnvironment && $cacheStore !== 'redis') {
            $violations[] = $this->violation(
                'CACHE_STORE',
                $cacheStore,
                'redis',
                'Release runtimes must use managed Redis for cache, rate limits, idempotency locks, and circuit-breaker state.'
            );
        }

        $errorTrackingProvider = strtolower((string) config('observability.error_tracking.provider', 'sentry'));
        $sentryBackendDsn = (string) config('observability.error_tracking.sentry_dsn', '');
        $sentryFrontendDsn = (string) config('observability.error_tracking.frontend_dsn', '');
        if ($isReleaseEnvironment) {
            if ($errorTrackingProvider !== 'sentry') {
                $violations[] = $this->violation(
                    'ERROR_TRACKING_PROVIDER',
                    $errorTrackingProvider,
                    'sentry',
                    'The chosen launch path uses Sentry for Laravel and React error tracking.'
                );
            }

            foreach ([
                'SENTRY_LARAVEL_DSN' => $sentryBackendDsn,
                'VITE_SENTRY_DSN' => $sentryFrontendDsn,
            ] as $key => $value) {
                $checked[] = $key;

                if ($this->isBlankOrPlaceholder($value)) {
                    $violations[] = $this->violation(
                        $key,
                        $this->redact($value),
                        'rotated Sentry DSN',
                        'Release error tracking must be active for backend and frontend runtime failures.'
                    );
                }
            }
        }

        $logAggregationProvider = strtolower((string) config('observability.logs.provider', 'better_stack'));
        $betterStackToken = (string) config('observability.logs.better_stack_source_token', '');
        $betterStackHost = (string) config('observability.logs.better_stack_ingesting_host', '');
        if ($isReleaseEnvironment) {
            if ($logAggregationProvider !== 'better_stack') {
                $violations[] = $this->violation(
                    'LOG_AGGREGATION_PROVIDER',
                    $logAggregationProvider,
                    'better_stack',
                    'The chosen launch path uses Better Stack for centralized logs and uptime correlation.'
                );
            }

            foreach ([
                'BETTER_STACK_SOURCE_TOKEN' => $betterStackToken,
                'BETTER_STACK_INGESTING_HOST' => $betterStackHost,
            ] as $key => $value) {
                $checked[] = $key;

                if ($this->isBlankOrPlaceholder($value)) {
                    $violations[] = $this->violation(
                        $key,
                        $this->redact($value),
                        'Better Stack source value',
                        'Centralized release logs must be routable before production traffic.'
                    );
                }
            }
        }

        $paymentProvider = strtolower((string) config('services.payment.provider', 'generic'));
        $paymentWebhookSecret = (string) config('services.payment.webhook_secret', '');
        if ($isReleaseEnvironment && ! in_array($paymentProvider, ['manual', 'none'], true)
            && $this->isBlankOrPlaceholder($paymentWebhookSecret)) {
            $violations[] = $this->violation(
                'PAYMENT_WEBHOOK_SECRET',
                $this->redact($paymentWebhookSecret),
                'rotated provider signing secret',
                'Payment webhooks must reject unsigned or replayed provider events.'
            );
        }

        if ($isReleaseEnvironment && $paymentProvider === 'stripe') {
            $usesStripePriceIds = (bool) config('services.stripe.use_price_ids', false);
            $checked[] = 'STRIPE_KEY';
            $checked[] = 'STRIPE_SECRET';
            $checked[] = 'STRIPE_WEBHOOK_SECRET';
            $checked[] = 'STRIPE_USE_PRICE_IDS';

            foreach ([
                'STRIPE_KEY' => (string) config('services.stripe.key', ''),
                'STRIPE_SECRET' => (string) config('services.stripe.secret', ''),
                'STRIPE_WEBHOOK_SECRET' => (string) config('services.stripe.webhook_secret', ''),
            ] as $key => $value) {
                if ($this->isBlankOrPlaceholder($value)) {
                    $violations[] = $this->violation(
                        $key,
                        $this->redact($value),
                        'rotated Stripe secret/price',
                        'Stripe cannot be marked active until its API, webhook, and SaaS subscription price values are present.'
                    );
                }
            }

            if ($usesStripePriceIds) {
                $checked[] = 'STRIPE_PRICE_PRO';
                $stripePricePro = (string) config('services.stripe.prices.pro', '');

                if ($this->isBlankOrPlaceholder($stripePricePro)) {
                    $violations[] = $this->violation(
                        'STRIPE_PRICE_PRO',
                        $this->redact($stripePricePro),
                        'rotated Stripe price id',
                        'Stripe price-id mode requires a real PRO Price ID. Otherwise leave STRIPE_USE_PRICE_IDS=false so Checkout uses the admin-configured amount.'
                    );
                }
            }
        }

        $mailMailer = (string) config('mail.default', 'log');
        if ($isReleaseEnvironment && $mailMailer !== 'resend') {
            $violations[] = $this->violation(
                'MAIL_MAILER',
                $mailMailer,
                'resend',
                'Release email should use Resend for transactional and lifecycle email; SendGrid/Mailchimp are not part of this tranche.'
            );
        }

        if ($isReleaseEnvironment) {
            $checked[] = 'RESEND_API_KEY';
            $checked[] = 'RESEND_WEBHOOK_SECRET';
            $checked[] = 'MAIL_FROM_ADDRESS';
            $resendKey = (string) config('services.resend.key', '');
            $resendWebhookSecret = (string) config('services.resend.webhook_secret', '');
            $mailFromAddress = (string) config('mail.from.address', '');

            if ($this->isBlankOrPlaceholder($resendKey)) {
                $violations[] = $this->violation(
                    'RESEND_API_KEY',
                    $this->redact($resendKey),
                    'rotated Resend API key',
                    'Resend mailer is active but cannot send safely without a real key.'
                );
            }

            if ($this->isBlankOrPlaceholder($resendWebhookSecret)) {
                $violations[] = $this->violation(
                    'RESEND_WEBHOOK_SECRET',
                    $this->redact($resendWebhookSecret),
                    'rotated Resend webhook secret',
                    'Email delivery events must be signed before they update CRM touchpoints.'
                );
            }

            if ($this->isBlankOrPlaceholder($mailFromAddress)) {
                $violations[] = $this->violation(
                    'MAIL_FROM_ADDRESS',
                    $this->redact($mailFromAddress),
                    'verified transactional sender',
                    'OTP, billing, and lifecycle email require a configured sender on a verified Resend domain.'
                );
            }
        }

        $whatsAppProvider = strtolower((string) config('observability.channels.whatsapp', 'whatsapp_cloud'));
        if ($isReleaseEnvironment) {
            if (! in_array($whatsAppProvider, ['whatsapp_cloud', 'missing', 'none', 'disabled'], true)) {
                $violations[] = $this->violation(
                    'CUSTOMER_AUTOMATION_WHATSAPP_PROVIDER',
                    $whatsAppProvider,
                    'whatsapp_cloud|missing|none|disabled',
                    'WhatsApp automation is optional at launch. If enabled, it must use Meta WhatsApp Cloud API directly, not Twilio.'
                );
            }

            if ($whatsAppProvider === 'whatsapp_cloud') {
                foreach ([
                    'WHATSAPP_CLOUD_ACCESS_TOKEN' => (string) config('services.whatsapp_cloud.access_token', ''),
                    'WHATSAPP_CLOUD_PHONE_NUMBER_ID' => (string) config('services.whatsapp_cloud.phone_number_id', ''),
                    'WHATSAPP_CLOUD_VERIFY_TOKEN' => (string) config('services.whatsapp_cloud.verify_token', ''),
                    'WHATSAPP_CLOUD_APP_SECRET' => (string) config('services.whatsapp_cloud.app_secret', ''),
                ] as $key => $value) {
                    $checked[] = $key;

                    if ($this->isBlankOrPlaceholder($value)) {
                        $violations[] = $this->violation(
                            $key,
                            $this->redact($value),
                            'rotated WhatsApp Cloud credential',
                            'WhatsApp Cloud inbound/outbound flows must be signed and provider-direct when enabled.'
                        );
                    }
                }
            }
        }

        $smsMarketingProvider = strtolower((string) config('observability.channels.sms', 'missing'));
        if ($isReleaseEnvironment && ! in_array($smsMarketingProvider, ['missing', 'none', 'disabled'], true)) {
            $violations[] = $this->violation(
                'SMS_MARKETING_PROVIDER',
                $smsMarketingProvider,
                'missing|none|disabled',
                'SMS marketing is intentionally out of scope because Honduras SMS segments are too expensive for campaign traffic.'
            );
        }

        $qdrantEnabled = (bool) config('ai-rag.private.qdrant.enabled', false);
        $qdrantDriver = (string) config('ai-rag.private.vector_store.driver', 'sql');
        if ($isReleaseEnvironment && ($qdrantEnabled || $qdrantDriver === 'qdrant')) {
            $checked[] = 'OPENAI_API_KEY';
            $checked[] = 'TENANT_PRIVATE_QDRANT_URL';
            $checked[] = 'TENANT_PRIVATE_QDRANT_API_KEY';
            $checked[] = 'TENANT_PRIVATE_QDRANT_COLLECTION';

            foreach ([
                'OPENAI_API_KEY' => (string) config('services.openai.api_key', ''),
                'TENANT_PRIVATE_QDRANT_API_KEY' => (string) config('ai-rag.private.qdrant.api_key', ''),
                'TENANT_PRIVATE_QDRANT_COLLECTION' => (string) config('ai-rag.scopes.tenant_private.collection', ''),
            ] as $key => $value) {
                if ($this->isBlankOrPlaceholder($value)) {
                    $violations[] = $this->violation(
                        $key,
                        $this->redact($value),
                        'rotated private RAG secret/config',
                        'Private RAG is enabled and must use isolated, non-placeholder runtime values.'
                    );
                }
            }

            $qdrantUrl = (string) config('ai-rag.private.qdrant.url', '');
            if ($this->isBlankOrPlaceholder($qdrantUrl) || filter_var($qdrantUrl, FILTER_VALIDATE_URL) === false) {
                $violations[] = $this->violation(
                    'TENANT_PRIVATE_QDRANT_URL',
                    $this->redact($qdrantUrl),
                    'valid Qdrant URL',
                    'Private RAG cannot validate Qdrant isolation without a real endpoint.'
                );
            }
        }

        $vapidPublic = (string) config('webpush.vapid.public_key', '');
        $vapidPrivate = (string) config('webpush.vapid.private_key', '');
        if ($isReleaseEnvironment && ($vapidPublic !== '' || $vapidPrivate !== '')) {
            $checked[] = 'VAPID_PUBLIC_KEY';
            $checked[] = 'VAPID_PRIVATE_KEY';

            foreach ([
                'VAPID_PUBLIC_KEY' => $vapidPublic,
                'VAPID_PRIVATE_KEY' => $vapidPrivate,
            ] as $key => $value) {
                if ($this->isBlankOrPlaceholder($value)) {
                    $violations[] = $this->violation(
                        $key,
                        $this->redact($value),
                        'rotated VAPID key',
                        'Push notifications must not run with blank or placeholder VAPID material.'
                    );
                }
            }
        }

        $payload = [
            'status' => $violations === [] ? 'ok' : 'failed',
            'environment' => $environment,
            'app_env' => $appEnvironment,
            'checked' => array_values(array_unique($checked)),
            'violations' => $violations,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($violations === []) {
            $this->info('Production configuration guard passed.');
        } else {
            $this->error('Production configuration guard failed.');

            foreach ($violations as $violation) {
                $this->line("- {$violation['key']}: {$violation['actual']} expected {$violation['expected']}. {$violation['reason']}");
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{key: string, actual: string, expected: string, reason: string}
     */
    private function violation(string $key, string $actual, string $expected, string $reason): array
    {
        return [
            'key' => $key,
            'actual' => $actual,
            'expected' => $expected,
            'reason' => $reason,
        ];
    }

    private function isBlankOrPlaceholder(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || $normalized === 'null') {
            return true;
        }

        foreach (['dummy', 'replace-me', 'changeme', 'placeholder', 'local_dev', 'example', 'xxxxx', '<', '>'] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function redact(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '(blank)';
        }

        if (strlen($value) <= 8) {
            return '(redacted)';
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }
}
