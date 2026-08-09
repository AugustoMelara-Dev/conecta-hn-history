<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelDeliveryService;
use App\Application\Channels\ChannelOutboundMessage;
use App\Infrastructure\Channels\ConfigChannelAdapterResolver;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Infrastructure\Channels\Voice\FailClosedVoiceAdapter;
use App\Infrastructure\Channels\Voice\VoiceProviderInterface;
use App\Infrastructure\Channels\WhatsApp\FailClosedWhatsAppAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ProviderFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_sms_and_voice_capabilities_deny_by_default_without_market_pack_or_credentials(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);

        $contexts = [
            'whatsapp' => new ChannelContext(
                tenantId: (string) $tenant->id,
                channel: 'whatsapp',
                connectionKey: 'wa-primary',
                marketCountryCode: 'CA',
                locale: 'en-CA',
                currency: 'CAD',
                timezone: 'America/Toronto',
                marketCapabilities: [],
                connection: [],
                authorization: new ChannelAuthorizationEnvelope(
                    tenantId: (string) $tenant->id,
                    actorId: (string) $actor->id,
                ),
            ),
            'sms' => new ChannelContext(
                tenantId: (string) $tenant->id,
                channel: 'sms',
                connectionKey: 'sms-primary',
                marketCountryCode: 'CA',
                locale: 'en-CA',
                currency: 'CAD',
                timezone: 'America/Toronto',
                marketCapabilities: [],
                connection: [],
                authorization: new ChannelAuthorizationEnvelope(
                    tenantId: (string) $tenant->id,
                    actorId: (string) $actor->id,
                ),
            ),
            'voice' => new ChannelContext(
                tenantId: (string) $tenant->id,
                channel: 'voice',
                connectionKey: 'voice-primary',
                marketCountryCode: 'CA',
                locale: 'en-CA',
                currency: 'CAD',
                timezone: 'America/Toronto',
                marketCapabilities: [],
                connection: [],
                authorization: new ChannelAuthorizationEnvelope(
                    tenantId: (string) $tenant->id,
                    actorId: (string) $actor->id,
                ),
            ),
        ];

        $adapters = [
            'whatsapp' => new FailClosedWhatsAppAdapter(credentialsConfigured: false),
            'sms' => new FailClosedSmsAdapter(credentialsConfigured: false),
            'voice' => new FailClosedVoiceAdapter(credentialsConfigured: false),
        ];

        foreach ($adapters as $channel => $adapter) {
            $decision = $adapter->capability($contexts[$channel]);
            self::assertFalse($decision->allowed, $channel);
            self::assertSame('market_capability_missing', $decision->reason, $channel);
        }
    }

    public function test_provider_outage_returns_an_unavailable_receipt_without_honduras_fallback(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignJapan($tenant);

        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'voice',
            connectionKey: 'voice-jp',
            marketCountryCode: 'JP',
            locale: 'ja-JP',
            currency: 'JPY',
            timezone: 'Asia/Tokyo',
            marketCapabilities: ['channels' => ['voice' => true]],
            connection: ['secret_reference_key' => 'voice.jp.primary'],
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $delivery = new ChannelDeliveryService($connectionService);
        $provider = new class implements VoiceProviderInterface
        {
            public int $calls = 0;

            public function send(array $payload): array
            {
                $this->calls++;

                throw new \RuntimeException('RAW_PROVIDER_TOKEN recipient +819012345678');
            }
        };
        $adapter = new FailClosedVoiceAdapter(
            credentialsConfigured: true,
            provider: $provider,
        );
        $message = new ChannelOutboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'voice',
            connectionKey: 'voice-jp',
            idempotencyKey: 'voice-out-001',
            externalThreadReference: 'call-thread-001',
            recipient: '+819012345678',
            content: 'Agent follow-up call',
        );
        $receipt = $delivery->send($adapter, $context, $message);
        $retry = $delivery->send($adapter, $context, $message);

        self::assertSame('provider_unavailable', $receipt->deliveryState);
        self::assertSame('provider_unavailable', $retry->deliveryState);
        self::assertSame($receipt->providerIdempotencyKey, $retry->providerIdempotencyKey);
        self::assertSame('JP', $receipt->metadata['market_country_code'] ?? null);
        self::assertSame('provider_unavailable', $receipt->metadata['reason'] ?? null);
        self::assertNotSame('HN', $receipt->metadata['market_country_code'] ?? null);
        self::assertSame(2, $provider->calls);

        $outbox = DB::table('channel_delivery_outbox')->where('idempotency_key', 'voice-out-001')->first();
        $attempt = DB::table('channel_delivery_attempts')->where('idempotency_key', 'voice-out-001')->first();
        self::assertNotNull($outbox);
        self::assertNotNull($attempt);
        self::assertSame('provider_unavailable', $outbox->last_error);
        self::assertSame('provider_unavailable', $attempt->last_error);
        self::assertSame(2, (int) $outbox->attempt_count);
        self::assertSame(2, (int) $attempt->attempt_count);
        self::assertStringNotContainsString('RAW_PROVIDER_TOKEN', (string) $outbox->last_error);
        self::assertStringNotContainsString('+819012345678', (string) $attempt->last_error);
    }

    public function test_resolver_records_unknown_provider_as_unavailable_without_fake_success(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'sms',
            connectionKey: 'sms-unknown',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            marketCapabilities: ['channels' => ['sms' => true]],
            connection: ['provider' => 'unknown.provider'],
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $delivery = new ChannelDeliveryService(
            new ChannelConnectionService,
            null,
            new ConfigChannelAdapterResolver,
        );

        $receipt = $delivery->sendResolved($context, new ChannelOutboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'sms',
            connectionKey: 'sms-unknown',
            idempotencyKey: 'sms-resolver-001',
            externalThreadReference: null,
            recipient: '+14165550100',
            content: 'Provider-safe message',
        ));

        self::assertSame('provider_unavailable', $receipt->deliveryState);
        self::assertSame('adapter_not_configured', $receipt->metadata['reason']);
        $this->assertDatabaseHas('channel_delivery_outbox', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => 'sms-resolver-001',
            'delivery_state' => 'provider_unavailable',
            'last_error' => 'provider_unavailable',
        ]);
    }
}
