<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Foundation\Application;

final class RuntimeOperationalReadiness
{
    /** @return array{schema:string,status:string,internal_ready:bool,checks:list<array{key:string,status:string,detail:string}>,providers:list<array{capability:string,status:string,provider:string}>,external_pending:list<string>} */
    public function inspect(): array
    {
        $checks = [
            $this->check('php_runtime', PHP_VERSION_ID >= 80400, PHP_VERSION),
            $this->check('laravel_runtime', str_starts_with(Application::VERSION, '13.'), Application::VERSION),
            $this->billingPolicy(),
            $this->channelPolicy(),
        ];
        $providers = $this->providers();
        $externalPending = array_values(array_map(
            static fn (array $provider): string => $provider['capability'].':'.$provider['provider'],
            array_filter($providers, static fn (array $provider): bool => $provider['status'] !== 'available'),
        ));
        $internalReady = ! array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail');

        return [
            'schema' => 'dovra.runtime-operational-readiness.v1',
            'status' => ! $internalReady ? 'internal_blocked' : ($externalPending === [] ? 'ready' : 'external_blocked'),
            'internal_ready' => $internalReady,
            'checks' => $checks,
            'providers' => $providers,
            'external_pending' => $externalPending,
        ];
    }

    /** @return array{key:string,status:string,detail:string} */
    private function billingPolicy(): array
    {
        $books = config('dovra_billing.price_books');
        if (! is_array($books) || $books === []) {
            return $this->check('billing_price_books', false, 'No market price books are configured.');
        }
        foreach ($books as $market => $book) {
            $currency = is_array($book) ? strtoupper(trim((string) ($book['currency'] ?? ''))) : '';
            if (! is_string($market) || preg_match('/\A[a-z]{2}\z/D', $market) !== 1 || preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
                return $this->check('billing_price_books', false, 'Every market requires an explicit ISO country and currency.');
            }
        }

        return $this->check('billing_price_books', true, count($books).' explicit market price book(s).');
    }

    /** @return array{key:string,status:string,detail:string} */
    private function channelPolicy(): array
    {
        $states = config('dovra_channels.contract.health.states');
        $valid = $states === ['available', 'unavailable', 'stale']
            && (int) config('dovra_channels.contract.health.stale_after_seconds', 0) > 0;

        return $this->check('channel_health_policy', $valid, $valid ? 'Health transitions are explicit and stale-aware.' : 'Channel health policy is invalid.');
    }

    /** @return list<array{capability:string,status:string,provider:string}> */
    private function providers(): array
    {
        $providers = [];
        foreach ((array) config('dovra_channels.fakes', []) as $channel => $definition) {
            if (is_array($definition) && ($definition['enabled'] ?? false) === true) {
                $providers[] = ['capability' => 'channel.'.$channel, 'status' => 'local_only', 'provider' => (string) ($definition['provider'] ?? 'fake')];
            }
        }
        foreach ((array) config('dovra_channels.ports', []) as $channel => $definition) {
            $providers[] = ['capability' => 'channel.'.$channel, 'status' => 'external_blocked', 'provider' => is_array($definition) ? (string) ($definition['provider'] ?? 'unavailable') : 'unavailable'];
        }
        foreach ((array) config('dovra_billing.market_providers', []) as $market => $definition) {
            $provider = is_array($definition) ? (string) ($definition['provider'] ?? 'unavailable') : 'unavailable';
            $providers[] = [
                'capability' => 'billing.'.$market,
                'status' => str_contains($provider, 'fake') ? 'local_only' : 'configured_unverified',
                'provider' => $provider,
            ];
        }

        $aiEnabled = (bool) config('ai.providers.openai.enabled', false);
        $aiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $providers[] = [
            'capability' => 'workforce.ai',
            'status' => $aiEnabled && $aiKey !== '' ? 'configured_unverified' : 'external_blocked',
            'provider' => $aiEnabled ? 'openai' : 'disabled',
        ];

        return $providers;
    }

    /** @return array{key:string,status:string,detail:string} */
    private function check(string $key, bool $passes, string $detail): array
    {
        return ['key' => $key, 'status' => $passes ? 'pass' : 'fail', 'detail' => $detail];
    }
}
