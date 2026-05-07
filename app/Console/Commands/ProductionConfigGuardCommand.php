<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

        if ($isProduction && (string) config('queue.default') === 'sync') {
            $violations[] = $this->issue('QUEUE_CONNECTION', 'sync', 'redis|database', 'Production jobs must not run inline with HTTP requests.');
        }

        if ($isProduction && in_array((string) config('cache.default'), ['array', 'file'], true)) {
            $violations[] = $this->issue('CACHE_STORE', (string) config('cache.default'), 'redis|database', 'Production cache must survive process restarts and support coordination.');
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

        if ($isProduction && ! filled(config('app.url'))) {
            $violations[] = $this->issue('APP_URL', 'missing', 'public https URL', 'Generated links and callback URLs need the canonical public URL.');
        }

        $payload = [
            'status' => $violations === [] ? 'ok' : 'failed',
            'environment' => $environment,
            'payment_provider' => $paymentProvider,
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
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
}
