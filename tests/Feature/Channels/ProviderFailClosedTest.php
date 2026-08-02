<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelDeliveryService;
use App\Application\Channels\ChannelOutboundMessage;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Infrastructure\Channels\Voice\FailClosedVoiceAdapter;
use App\Infrastructure\Channels\Voice\VoiceProviderInterface;
use App\Infrastructure\Channels\WhatsApp\FailClosedWhatsAppAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $adapter = new FailClosedVoiceAdapter(
            credentialsConfigured: true,
            provider: new class implements VoiceProviderInterface
            {
                public function send(array $payload): array
                {
                    throw new \RuntimeException('provider outage');
                }
            },
        );

        $receipt = $delivery->send(
            $adapter,
            $context,
            new ChannelOutboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'voice',
                connectionKey: 'voice-jp',
                idempotencyKey: 'voice-out-001',
                externalThreadReference: 'call-thread-001',
                recipient: '+819012345678',
                content: 'Agent follow-up call',
            ),
        );

        self::assertSame('provider_unavailable', $receipt->deliveryState);
        self::assertSame('JP', $receipt->metadata['market_country_code'] ?? null);
        self::assertSame('provider_unavailable', $receipt->metadata['reason'] ?? null);
        self::assertNotSame('HN', $receipt->metadata['market_country_code'] ?? null);
    }
}
