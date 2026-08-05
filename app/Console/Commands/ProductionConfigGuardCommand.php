<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductionConfigGuardCommand extends Command
{
    protected $signature = 'config:production-guard
        {--env= : Environment to validate, defaults to APP_ENV}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Fail release when production-critical runtime configuration is unsafe.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $isProduction = $environment === 'production';
        $paymentProvider = strtolower((string) config('services.payment.provider', 'generic'));
        $violations = [];
        $warnings = [];

        if ($isProduction && config('app.debug') === true) {
            $violations[] = $this->issue('APP_DEBUG', 'true', 'false', 'Production must not render debug traces or verbose exception payloads.');
        }

        if ($isProduction && (string) config('queue.default') !== 'redis') {
            $violations[] = $this->issue('QUEUE_CONNECTION', (string) config('queue.default'), 'redis', 'Horizon requires Redis and production jobs must not run inline with HTTP requests.');
        }

        if ($isProduction) {
            $redisUrl = (string) config('database.redis.default.url', '');
            $redisScheme = strtolower((string) config('database.redis.default.scheme', ''));
            $redisUrlScheme = strtolower((string) parse_url($redisUrl, PHP_URL_SCHEME));

            if (! in_array($redisScheme, ['tls', 'rediss'], true)
                && ! in_array($redisUrlScheme, ['tls', 'rediss'], true)) {
                $violations[] = $this->issue('REDIS_SCHEME', $redisScheme ?: 'missing', 'tls|rediss', 'Queue, cache and Horizon traffic must be encrypted in transit.');
            }
        }

        if ($isProduction && in_array((string) config('cache.default'), ['array', 'file'], true)) {
            $violations[] = $this->issue('CACHE_STORE', (string) config('cache.default'), 'redis|database', 'Production cache must survive process restarts and support coordination.');
        }

        if ($isProduction && (string) config('session.driver') !== 'database') {
            $violations[] = $this->issue('SESSION_DRIVER', (string) config('session.driver'), 'database', 'Account recovery must be able to revoke every active web session by user.');
        }

        if ($paymentProvider === 'stripe') {
            foreach ([
                'STRIPE_KEY' => config('services.stripe.key'),
                'STRIPE_SECRET' => config('services.stripe.secret'),
                'STRIPE_WEBHOOK_SECRET' => config('services.stripe.webhook_secret'),
                'STRIPE_PRICE_PRO' => config('services.stripe.price_pro'),
            ] as $key => $value) {
                if (! filled($value)) {
                    $violations[] = $this->issue($key, 'missing', 'configured', 'Stripe billing cannot be enabled without this secret/config value.');
                }
            }
        }

        if ($isProduction && $paymentProvider !== 'stripe') {
            $warnings[] = $this->issue('PAYMENT_PROVIDER', $paymentProvider, 'stripe', 'PRO checkout will remain in manual fallback until Stripe is enabled.');
        }

        if ($isProduction && ! filled(config('app.key'))) {
            $violations[] = $this->issue('APP_KEY', 'missing', 'configured', 'Encryption, sessions and signed URLs require a stable app key.');
        }

        $appUrl = (string) config('app.url', '');
        $appUrlIsHttps = filter_var($appUrl, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) === 'https'
            && filled(parse_url($appUrl, PHP_URL_HOST));

        if ($isProduction && ! $appUrlIsHttps) {
            $violations[] = $this->issue(
                'APP_URL',
                $appUrl === '' ? 'missing' : 'invalid or non-HTTPS',
                'canonical public HTTPS URL',
                'Generated links, cookies, OAuth callbacks and signed URLs require one secure canonical origin.',
            );
        }

        if ($isProduction && config('session.secure') !== true) {
            $violations[] = $this->issue('SESSION_SECURE_COOKIE', 'false', 'true', 'Authentication cookies must never traverse plaintext HTTP.');
        }

        if ($isProduction && config('session.http_only') !== true) {
            $violations[] = $this->issue('SESSION_HTTP_ONLY', 'false', 'true', 'Browser JavaScript must not be able to read authentication cookies.');
        }

        $sameSite = strtolower((string) config('session.same_site', ''));
        if ($isProduction && ! in_array($sameSite, ['lax', 'strict'], true)) {
            $violations[] = $this->issue('SESSION_SAME_SITE', $sameSite ?: 'missing', 'lax|strict', 'First-party sessions require a CSRF-resistant SameSite policy.');
        }

        if ($isProduction && config('security.hsts.enabled') !== true) {
            $violations[] = $this->issue('SECURITY_HSTS_ENABLED', 'false', 'true', 'Production HTTPS must resist protocol downgrade after the first secure visit.');
        }

        if ($isProduction && config('security.admin_mfa_required') !== true) {
            $violations[] = $this->issue('SECURITY_ADMIN_MFA_REQUIRED', 'false', 'true', 'Platform administrators require a phishing-resistant second barrier beyond the account password.');
        }
        if ($isProduction && ! filled(config('sentry.dsn'))) {
            $violations[] = $this->issue('SENTRY_LARAVEL_DSN', 'missing', 'configured', 'Unhandled production failures require an external error sink and alerting path.');
        }

        if ($isProduction && config('sentry.send_default_pii') !== false) {
            $violations[] = $this->issue('SENTRY_SEND_DEFAULT_PII', 'true', 'false', 'Error telemetry must not export customer identity or private business data by default.');
        }

        if ($isProduction && config('observability.opentelemetry.enabled') !== true) {
            $violations[] = $this->issue('OTEL_PHP_AUTOLOAD_ENABLED', 'false', 'true', 'Production requests and workers require vendor-neutral distributed traces.');
        }

        $otelEndpoint = (string) config('observability.opentelemetry.endpoint', '');
        if ($isProduction && (
            filter_var($otelEndpoint, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($otelEndpoint, PHP_URL_SCHEME)) !== 'https'
        )) {
            $violations[] = $this->issue('OTEL_EXPORTER_OTLP_ENDPOINT', $otelEndpoint === '' ? 'missing' : 'invalid or non-HTTPS', 'HTTPS OTLP endpoint', 'Trace export can include operational metadata and must be encrypted in transit.');
        }
        $databaseConnection = (string) config('database.default');
        $databaseDriver = (string) config("database.connections.{$databaseConnection}.driver");

        if ($isProduction && $databaseDriver !== 'pgsql') {
            $violations[] = $this->issue('DB_CONNECTION', $databaseDriver ?: 'missing', 'pgsql', 'The canonical production database requires PostgreSQL constraints, pgvector readiness and tenant RLS.');
        }

        if ($isProduction && $databaseDriver === 'pgsql'
            && ! in_array((string) config("database.connections.{$databaseConnection}.sslmode"), ['require', 'verify-ca', 'verify-full'], true)) {
            $violations[] = $this->issue('DB_SSLMODE', (string) config("database.connections.{$databaseConnection}.sslmode"), 'require|verify-ca|verify-full', 'Production database credentials and tenant data must be encrypted in transit.');
        }
        if ($isProduction && $databaseDriver === 'pgsql' && config('tenancy.postgres_rls.enabled') !== true) {
            $violations[] = $this->issue('POSTGRES_RLS_ENABLED', 'false', 'true', 'High-risk tenant tables must enforce row-level security in production.');
        }

        if ($isProduction && $databaseDriver === 'pgsql' && config('tenancy.postgres_rls.enabled') === true) {
            try {
                $runtimeRole = DB::selectOne('select current_user as role_name, rolsuper, rolbypassrls from pg_roles where rolname = current_user');

                if ((bool) ($runtimeRole->rolsuper ?? false) || (bool) ($runtimeRole->rolbypassrls ?? false)) {
                    $violations[] = $this->issue('DB_RUNTIME_ROLE', (string) ($runtimeRole->role_name ?? 'unknown'), 'NOSUPERUSER NOBYPASSRLS role', 'The public and tenant runtime role must be unable to bypass RLS.');
                }
            } catch (\Throwable $exception) {
                $violations[] = $this->issue('DB_RUNTIME_ROLE_CHECK', 'unavailable', 'successful role inspection', 'Release verification must reach PostgreSQL using the real runtime credentials: '.$exception->getMessage());
            }

            $platformUsername = (string) config('database.connections.pgsql_platform.username');
            $runtimeUsername = (string) config("database.connections.{$databaseConnection}.username");

            if ($platformUsername === '' || hash_equals($runtimeUsername, $platformUsername)) {
                $violations[] = $this->issue('DB_PLATFORM_USERNAME', $platformUsername === '' ? 'missing' : 'same as runtime', 'dedicated platform role', 'Platform-wide administration must use separate audited credentials.');
            } else {
                try {
                    $platformRole = DB::connection('pgsql_platform')->selectOne('select current_user as role_name, rolsuper, rolbypassrls from pg_roles where rolname = current_user');

                    if ((bool) ($platformRole->rolsuper ?? false) || ! (bool) ($platformRole->rolbypassrls ?? false)) {
                        $violations[] = $this->issue('DB_PLATFORM_ROLE', (string) ($platformRole->role_name ?? 'unknown'), 'NOSUPERUSER BYPASSRLS role', 'Platform administration needs an explicit non-superuser RLS-bypass role, never the database owner.');
                    }
                } catch (\Throwable $exception) {
                    $violations[] = $this->issue('DB_PLATFORM_ROLE_CHECK', 'unavailable', 'successful role inspection', 'Release verification must reach PostgreSQL using the dedicated platform credentials: '.$exception->getMessage());
                }
            }
        }
        if ($isProduction && config('auth.social_login_enabled') === true) {
            $violations[] = $this->issue(
                'SOCIAL_LOGIN_ENABLED',
                'true',
                'false',
                'The legacy social API trusts caller-supplied identity claims. Keep it disabled until provider tokens are verified server-side.',
            );
        }

        $capabilities = (array) config('ai.capabilities', []);
        $enabledCapabilities = array_filter(
            $capabilities,
            static fn (mixed $definition): bool => is_array($definition) && (bool) ($definition['enabled'] ?? false),
        );

        if ($enabledCapabilities !== [] && ! (bool) config('ai.enabled', false)) {
            $violations[] = $this->issue('AI_ENABLED', 'false', 'true', 'Enabled AI capabilities must remain behind the global gateway switch.');
        }

        if ((bool) config('ai.enabled', false)) {
            if ((bool) config('ai.registry.bootstrap_enabled', false)) {
                $violations[] = $this->issue(
                    'AI_REGISTRY_BOOTSTRAP_ENABLED',
                    'true',
                    'false',
                    'Governed AI releases must use the registry lifecycle, not bootstrap shortcuts.',
                );
            }

            $conversationRetentionDays = (int) config('ai.retention.conversation_days', 0);
            if ($conversationRetentionDays < 1) {
                $violations[] = $this->issue(
                    'AI_CONVERSATION_RETENTION_DAYS',
                    (string) $conversationRetentionDays,
                    '>= 1',
                    'Conversation retention must be an explicit positive policy window.',
                );
            }

            if (! filled((string) config('ai.execution_retention.encryption_key_version', ''))) {
                $violations[] = $this->issue(
                    'AI_ENCRYPTION_KEY_VERSION',
                    'missing',
                    'configured',
                    'Execution retention must record the active encryption key version.',
                );
            }

            if ($isProduction && ! in_array('production', (array) config('ai.allowed_environments', []), true)) {
                $violations[] = $this->issue('AI_ALLOWED_ENVIRONMENTS', 'production missing', 'include production', 'The AI gateway checks the real runtime environment.');
            }

            foreach ($enabledCapabilities as $capability => $definition) {
                $provider = (string) ($definition['provider'] ?? '');
                $cost = (float) ($definition['max_cost_usd'] ?? 0);
                $rate = (int) ($definition['requests_per_minute'] ?? 0);
                $models = $definition['models'] ?? null;
                $billingMode = $definition['billing_mode'] ?? null;

                if (! in_array($billingMode, ['platform', 'tenant'], true)) {
                    $violations[] = $this->issue("AI_{$capability}_BILLING_MODE", (string) ($billingMode ?? 'missing'), 'platform|tenant', 'Every enabled capability must derive its payer authoritatively.');
                }

                if (! is_array($models)
                    || ! array_is_list($models)
                    || $models === []
                    || array_filter(
                        $models,
                        static fn (mixed $model): bool => is_string($model) && trim($model) !== '',
                    ) !== $models
                    || count(array_unique($models)) !== count($models)) {
                    $violations[] = $this->issue("AI_{$capability}_MODELS", 'missing or invalid', 'one or more non-empty unique model identifiers', 'Every enabled capability needs an explicit model allowlist.');
                }

                if ($provider === '' || ! (bool) config("ai.providers.{$provider}.enabled", false)) {
                    $violations[] = $this->issue("AI_{$capability}_PROVIDER", $provider ?: 'missing', 'enabled provider', 'Every enabled capability needs an enabled provider.');
                }
                if (! is_finite($cost) || $cost <= 0) {
                    $violations[] = $this->issue("AI_{$capability}_MAX_COST", (string) $cost, '> 0', 'Every provider call requires a worst-case credit reservation.');
                }
                if ($rate < 1) {
                    $violations[] = $this->issue("AI_{$capability}_RATE_LIMIT", (string) $rate, '>= 1', 'Every enabled capability requires a server-side rate limit.');
                }
            }

            if ((bool) config('ai.providers.openai.enabled', false) && ! filled(config('services.openai.api_key'))) {
                $violations[] = $this->issue('OPENAI_API_KEY', 'missing', 'configured', 'The OpenAI provider cannot be enabled without a secret.');
            }

            if ((bool) config('ai.providers.openai.enabled', false)) {
                $secretReference = (string) config('ai.providers.openai.secret_reference', '');
                if (! filled($secretReference) || $this->looksLikeCredential($secretReference)) {
                    $violations[] = $this->issue(
                        'AI_OPENAI_SECRET_REFERENCE',
                        filled($secretReference) ? 'unsafe secret reference' : 'missing',
                        'managed secret reference',
                        'Enabled providers must resolve credentials through managed references, never inline secrets.',
                    );
                }

                $endpoint = (string) config('services.openai.chat_completions_endpoint', '');
                if (filter_var($endpoint, FILTER_VALIDATE_URL) === false
                    || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
                    $violations[] = $this->issue(
                        'OPENAI_CHAT_COMPLETIONS_ENDPOINT',
                        $endpoint === '' ? 'missing' : 'invalid',
                        'valid HTTPS URL',
                        'Structured AI calls require an explicit secure endpoint.',
                    );
                }

                $openAiModels = [];
                foreach ($enabledCapabilities as $definition) {
                    if (($definition['provider'] ?? null) !== 'openai' || ! is_array($definition['models'] ?? null)) {
                        continue;
                    }

                    foreach ($definition['models'] as $model) {
                        if (is_string($model) && $model !== '') {
                            $openAiModels[$model] = true;
                        }
                    }
                }

                foreach (array_keys($openAiModels) as $model) {
                    $pricing = config("ai.providers.openai.models.{$model}");
                    $inputRate = is_array($pricing) ? ($pricing['input_usd_per_million'] ?? null) : null;
                    $outputRate = is_array($pricing) ? ($pricing['output_usd_per_million'] ?? null) : null;

                    if (! is_numeric($inputRate) || ! is_numeric($outputRate)
                        || ! is_finite((float) $inputRate) || ! is_finite((float) $outputRate)
                        || (float) $inputRate <= 0 || (float) $outputRate <= 0) {
                        $violations[] = $this->issue(
                            "OPENAI_MODEL_PRICING_{$model}",
                            'missing or non-positive',
                            'positive input/output USD rates per million tokens',
                            'Every enabled model needs auditable cost accounting.',
                        );
                    }
                }
            }

            if ((bool) config('ai.capabilities.public_discovery.enabled', false)) {
                $billingTenantId = (string) config('ai.public.billing_tenant_id');

                if (! Str::isUuid($billingTenantId)
                    || ! DB::table('tenants')->where('id', $billingTenantId)->exists()) {
                    $violations[] = $this->issue(
                        'AI_PUBLIC_BILLING_TENANT_ID',
                        'missing or invalid',
                        'existing platform tenant UUID',
                        'Public conversations and credits require explicit ownership.',
                    );
                }
            }

            if ((bool) config('ai.capabilities.private_tenant_agent.enabled', false)) {
                if (! (bool) config('ai.private.enabled', false)) {
                    $violations[] = $this->issue('AI_PRIVATE_ENABLED', 'false', 'true', 'Private tenant AI has an independent kill switch.');
                }
                $privateTenantAllowlist = (array) config('ai.private.tenant_allowlist', []);
                $validPrivateTenantAllowlist = $privateTenantAllowlist !== []
                    && array_is_list($privateTenantAllowlist)
                    && collect($privateTenantAllowlist)->every(
                        static fn (mixed $tenantId): bool => is_string($tenantId) && Str::isUuid($tenantId),
                    )
                    && count($privateTenantAllowlist) === count(array_unique($privateTenantAllowlist));

                if (! $validPrivateTenantAllowlist) {
                    $violations[] = $this->issue('AI_PRIVATE_TENANT_ALLOWLIST', 'empty or invalid', 'one or more unique tenant UUIDs', 'Private AI is rolled out through an explicit UUID-only tenant allowlist.');
                }
            }
        }

        if ((bool) config('ai.tool_confirmations.enabled', false)) {
            if (! (bool) config('ai.enabled', false)
                || ! (bool) config('ai.private.enabled', false)) {
                $violations[] = $this->issue(
                    'AI_TOOL_CONFIRMATIONS_ENABLED',
                    'true without AI/private gateway',
                    'AI_ENABLED=true and AI_PRIVATE_ENABLED=true',
                    'Confirmed tools must remain behind both AI kill switches and the private tenant access gate.',
                );
            }

            $confirmationTtl = (int) config('ai.tool_confirmations.ttl_seconds', 0);
            if ($confirmationTtl < 60 || $confirmationTtl > 3600) {
                $violations[] = $this->issue(
                    'AI_TOOL_CONFIRMATION_TTL_SECONDS',
                    (string) $confirmationTtl,
                    '60..3600',
                    'Actor-bound confirmations must expire quickly without making review impractical.',
                );
            }

            $confirmationRate = (int) config('ai.tool_confirmations.requests_per_minute', 0);
            if ($confirmationRate < 1 || $confirmationRate > 60) {
                $violations[] = $this->issue(
                    'AI_TOOL_CONFIRMATION_REQUESTS_PER_MINUTE',
                    (string) $confirmationRate,
                    '1..60',
                    'Confirmation preparation and token attempts need a bounded tenant and actor rate.',
                );
            }
        }
        $knowledgeEnabled = (bool) config('ai.knowledge.enabled', false);
        if ($knowledgeEnabled) {
            if (! (bool) config('ai.enabled', false)) {
                $violations[] = $this->issue('AI_ENABLED', 'false', 'true', 'Semantic retrieval must remain behind the global AI kill switch.');
            }

            $knowledgeProvider = (string) config('ai.knowledge.provider', '');
            if ($knowledgeProvider === '' || ! (bool) config("ai.providers.{$knowledgeProvider}.enabled", false)) {
                $violations[] = $this->issue('AI_EMBEDDING_PROVIDER', $knowledgeProvider ?: 'missing', 'enabled provider', 'Knowledge indexing requires an explicitly enabled embedding provider.');
            }

            if ((int) config('ai.knowledge.embedding_dimensions', 0) !== 1536) {
                $violations[] = $this->issue('AI_EMBEDDING_DIMENSIONS', (string) config('ai.knowledge.embedding_dimensions'), '1536', 'The configured dimensions must match the PostgreSQL vector column and index.');
            }

            if (! (bool) config('ai.knowledge.metering_enabled', false)) {
                $violations[] = $this->issue('AI_EMBEDDING_METERING_ENABLED', 'false', 'true', 'Every embedding call must reserve and settle commercial credits.');
            }

            foreach ([
                'AI_EMBEDDING_MAX_COST_USD' => config('ai.knowledge.max_cost_usd'),
                'AI_EMBEDDING_INPUT_USD_PER_MILLION' => config('ai.knowledge.input_usd_per_million'),
            ] as $key => $value) {
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                    $violations[] = $this->issue($key, (string) $value, '> 0', 'Embedding usage requires positive auditable cost controls.');
                }
            }

            if (! Str::isUuid((string) config('ai.public.billing_tenant_id'))) {
                $violations[] = $this->issue('AI_PUBLIC_BILLING_TENANT_ID', 'missing or invalid', 'platform tenant UUID', 'Anonymous semantic retrieval requires an authoritative platform payer.');
            }

            if ($knowledgeProvider === 'openai') {
                $endpoint = (string) config('services.openai.embeddings_endpoint', '');
                if (filter_var($endpoint, FILTER_VALIDATE_URL) === false
                    || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
                    $violations[] = $this->issue('OPENAI_EMBEDDINGS_ENDPOINT', $endpoint === '' ? 'missing' : 'invalid or non-HTTPS', 'valid HTTPS URL', 'Embedding requests must use an explicit encrypted endpoint.');
                }
            }

            if ($isProduction && $databaseDriver === 'pgsql') {
                try {
                    $vectorExtension = DB::selectOne("select extversion from pg_extension where extname = 'vector'");
                    if (! is_string($vectorExtension->extversion ?? null) || $vectorExtension->extversion === '') {
                        $violations[] = $this->issue('PGVECTOR_EXTENSION', 'missing', 'installed', 'Semantic retrieval requires the vector extension in the canonical database.');
                    }
                } catch (\Throwable $exception) {
                    $violations[] = $this->issue('PGVECTOR_EXTENSION_CHECK', 'unavailable', 'successful extension inspection', 'Release verification must prove pgvector availability: '.$exception->getMessage());
                }
            }
        }
        $payload = [
            'status' => $violations === [] ? 'ok' : 'failed',
            'environment' => $environment,
            'payment_provider' => $paymentProvider,
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
            'debug' => config('app.debug'),
            'violations' => $violations,
            'warnings' => $warnings,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $violations === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($warnings as $warning) {
            $this->warn("- {$warning['key']}: {$warning['actual']} expected {$warning['expected']}. {$warning['reason']}");
        }

        if ($violations === []) {
            $this->info('Production config guard passed.');

            return self::SUCCESS;
        }

        $this->error('Production config guard failed.');

        foreach ($violations as $violation) {
            $this->line("- {$violation['key']}: {$violation['actual']} expected {$violation['expected']}. {$violation['reason']}");
        }

        return self::FAILURE;
    }

    /**
     * @return array{key: string, actual: string, expected: string, reason: string}
     */
    private function issue(string $key, string $actual, string $expected, string $reason): array
    {
        return compact('key', 'actual', 'expected', 'reason');
    }

    private function looksLikeCredential(string $value): bool
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return false;
        }

        return Str::startsWith($normalized, ['sk-', 'rk-', 'pk-', '-----BEGIN'])
            || str_contains($normalized, '://')
            || preg_match('/\s/', $normalized) === 1;
    }
}
