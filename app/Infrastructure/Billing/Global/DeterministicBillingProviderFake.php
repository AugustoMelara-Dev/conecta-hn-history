<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Global;

use App\Application\Billing\Global\GlobalEntitlementService;
use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Domain\Billing\Global\SubscriptionCheckoutContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeterministicBillingProviderFake
{
    public function __construct(
        private readonly GlobalEntitlementService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{payload:string,signature:string}
     */
    public function signedWebhook(string $marketKey, array $payload): array
    {
        $config = $this->providerConfig($marketKey);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'payload' => $json,
            'signature' => hash_hmac('sha256', $json, $config['signing_secret']),
        ];
    }

    public function handleWebhook(string $marketKey, string $payload, string $signature): void
    {
        $config = $this->providerConfig($marketKey);
        $expected = hash_hmac('sha256', $payload, $config['signing_secret']);

        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('invalid webhook signature');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $eventId = trim((string) ($decoded['event_id'] ?? ''));
        $tenantId = trim((string) ($decoded['tenant_id'] ?? ''));
        $plan = trim((string) ($decoded['plan'] ?? ''));
        $status = trim((string) ($decoded['status'] ?? ''));
        $eventType = trim((string) ($decoded['event_type'] ?? ''));

        if ($eventId === '' || $tenantId === '' || $plan === '' || $status === '' || $eventType === '') {
            throw new \RuntimeException('webhook payload is incomplete');
        }

        DB::transaction(function () use ($marketKey, $config, $payload, $signature, $decoded, $eventId, $tenantId, $plan, $status, $eventType): void {
            DB::table('global_billing_market_providers')->updateOrInsert(
                ['market_key' => strtolower($marketKey)],
                [
                    'provider_key' => (string) $config['provider'],
                    'checkout_mode' => (string) $config['checkout_mode'],
                    'signing_secret' => (string) $config['signing_secret'],
                    'created_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ],
            );

            $existing = DB::table('global_billing_lifecycle_events')
                ->where('provider_key', (string) $config['provider'])
                ->where('provider_event_id', $eventId)
                ->exists();

            if ($existing) {
                return;
            }

            DB::table('global_billing_lifecycle_events')->insert([
                'id' => (string) Str::uuid(),
                'market_key' => strtolower($marketKey),
                'provider_key' => (string) $config['provider'],
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
                'signature' => $signature,
                'payload_sha256' => hash('sha256', $payload),
                'tenant_id' => $tenantId,
                'authenticity_status' => 'verified',
                'replayed' => false,
                'processed_at' => now()->utc(),
                'payload' => $payload,
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

            $tenant = GlobalTenantContext::fromArray([
                'tenant_id' => $tenantId,
                'market' => $this->marketContext($marketKey),
                'plan' => $plan,
                'status' => $status,
                'grace_ends_at' => $decoded['grace_ends_at'] ?? null,
            ]);

            $decoded['provider'] = $config['provider'];
            $this->entitlements->synchronizeFromWebhook($tenant, $decoded);
        }, 3);
    }

    public function checkoutContract(GlobalTenantContext $tenant): SubscriptionCheckoutContract
    {
        $config = $this->providerConfig(strtolower($tenant->market()->countryCode));

        DB::table('global_billing_market_providers')->updateOrInsert(
            ['market_key' => strtolower($tenant->market()->countryCode)],
            [
                'provider_key' => (string) $config['provider'],
                'checkout_mode' => (string) $config['checkout_mode'],
                'signing_secret' => (string) $config['signing_secret'],
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ],
        );

        return new SubscriptionCheckoutContract(
            mode: (string) $config['checkout_mode'],
            providerKey: (string) $config['provider'],
        );
    }

    /**
     * @return array{provider:string,checkout_mode:string,signing_secret:string}
     */
    private function providerConfig(string $marketKey): array
    {
        $config = config('dovra_billing.market_providers.'.strtolower($marketKey));

        if (! is_array($config)) {
            throw new \RuntimeException("No billing provider is configured for market [{$marketKey}].");
        }

        return [
            'provider' => (string) ($config['provider'] ?? 'deterministic-fake'),
            'checkout_mode' => (string) ($config['checkout_mode'] ?? 'external'),
            'signing_secret' => (string) ($config['signing_secret'] ?? 'test-secret'),
        ];
    }

    private function marketContext(string $marketKey): MarketContext
    {
        $country = strtoupper($marketKey);
        $book = (new ConfigPriceBookReader)->forMarket(new MarketContext($country, 'en-'.$country, 'USD', 'UTC', 'billing_webhook'));

        return new MarketContext(
            countryCode: $country,
            localeCode: 'en-'.$country,
            currencyCode: $book->currencyCode(),
            timezone: 'UTC',
            source: 'billing_webhook',
        );
    }
}
