<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelConsentService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelIdentityResolver;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelIdentityConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03T15:00:00Z', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_email_and_phone_identities_are_normalized_per_tenant_and_do_not_leak(): void
    {
        $actorA = User::factory()->create();
        $actorB = User::factory()->create();
        $tenantA = Tenant::factory()->forUser($actorA)->create();
        $tenantB = Tenant::factory()->forUser($actorB)->create();
        GlobalMarketFixture::assignCanada($tenantA);
        GlobalMarketFixture::assignJapan($tenantB);

        $resolver = new ChannelIdentityResolver;

        $identityA = $resolver->resolve(
            $this->context($tenantA, $actorA, 'email', 'email-a', 'CA', 'en-CA', 'CAD', 'America/Toronto'),
            new \App\Application\Channels\NormalizedInboundMessage(
                adapterKey: 'email.fake',
                tenantId: (string) $tenantA->id,
                channel: 'email',
                connectionKey: 'email-a',
                externalMessageId: 'identity-a',
                externalThreadReference: 'thread-a',
                normalizedIdentity: 'buyer@example.test',
                identityType: 'email',
                content: 'hello',
                attachments: [],
                consentStatus: 'granted',
                deliveryState: 'delivered',
                providerEventId: 'evt-a',
                costAmount: '0.00',
                costCurrency: 'USD',
                marketCountryCode: 'CA',
                locale: 'en-CA',
                timezone: 'America/Toronto',
                metadata: [],
            ),
        );
        $identityB = $resolver->resolve(
            $this->context($tenantB, $actorB, 'sms', 'sms-b', 'JP', 'ja-JP', 'JPY', 'Asia/Tokyo'),
            new \App\Application\Channels\NormalizedInboundMessage(
                adapterKey: 'sms.fail_closed',
                tenantId: (string) $tenantB->id,
                channel: 'sms',
                connectionKey: 'sms-b',
                externalMessageId: 'identity-b',
                externalThreadReference: 'thread-b',
                normalizedIdentity: '+819012345678',
                identityType: 'phone_e164',
                content: 'こんにちは',
                attachments: [],
                consentStatus: 'granted',
                deliveryState: 'delivered',
                providerEventId: 'evt-b',
                costAmount: '0.00',
                costCurrency: 'USD',
                marketCountryCode: 'JP',
                locale: 'ja-JP',
                timezone: 'Asia/Tokyo',
                metadata: [],
            ),
        );

        self::assertNotSame($identityA->tenantId, $identityB->tenantId);
        self::assertSame('buyer@example.test', $identityA->normalizedValue);
        self::assertSame('+819012345678', $identityB->normalizedValue);
        self::assertSame(2, DB::table('channel_identities')->count());
    }

    public function test_sms_consent_is_persisted_with_market_and_connection_lineage(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignJapan($tenant);
        $this->setMarketCapabilities($tenant, [
            'channels' => [
                'sms' => true,
            ],
        ]);

        $context = $this->context(
            $tenant,
            $actor,
            'sms',
            'sms-primary',
            'JP',
            'ja-JP',
            'JPY',
            'Asia/Tokyo',
            [
                'secret_reference_key' => 'sms.jp.primary',
                'provider' => 'market-pack-sms',
            ],
            [
                'channels' => [
                    'sms' => true,
                ],
            ],
        );
        $connectionService = new ChannelConnectionService;
        $connectionId = $connectionService->connect($context)->id;
        $adapter = new FailClosedSmsAdapter(credentialsConfigured: true, webhookSecret: 'sms-webhook-secret');
        $payload = json_encode(['event' => 'message.received', 'id' => 'sms-in-001'], JSON_THROW_ON_ERROR);

        $normalized = $adapter->receive($context, self::signedMessage(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'sms',
            connectionKey: 'sms-primary',
            externalMessageId: 'sms-in-001',
            externalThreadReference: 'sms-thread-001',
            identityType: 'phone_e164',
            identityValue: ' 090-1234-5678 ',
            content: 'YES',
            consentStatus: 'granted',
            webhookEventId: 'sms-in-001',
            webhookPayload: $payload,
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'sms-replay-001',
        ), 'sms-webhook-secret'));

        $identity = (new ChannelIdentityResolver)->resolve($context, $normalized);
        $consent = (new ChannelConsentService)->record($context, $identity, $normalized, 'messaging');

        self::assertSame('granted', $consent->status);
        self::assertSame('messaging', $consent->purpose);
        $this->assertDatabaseHas('channel_connections', [
            'id' => $connectionId,
            'tenant_id' => $tenant->id,
            'channel' => 'sms',
            'provider_key' => 'market-pack-sms',
            'secret_reference_key' => 'sms.jp.primary',
        ]);
        $this->assertDatabaseHas('channel_consents', [
            'tenant_id' => $tenant->id,
            'channel' => 'sms',
            'identity_id' => $identity->id,
            'purpose' => 'messaging',
            'status' => 'granted',
            'market_country_code' => 'JP',
            'connection_key' => 'sms-primary',
        ]);
    }

    private function context(
        Tenant $tenant,
        User $actor,
        string $channel,
        string $connectionKey,
        string $country,
        string $locale,
        string $currency,
        string $timezone,
        array $connection = [],
        array $capabilities = [],
    ): ChannelContext {
        return new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: $connectionKey,
            marketCountryCode: $country,
            locale: $locale,
            currency: $currency,
            timezone: $timezone,
            marketCapabilities: $capabilities,
            connection: $connection,
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
    }

    private function setMarketCapabilities(Tenant $tenant, array $capabilities): void
    {
        $versionId = DB::table('tenant_markets')
            ->where('tenant_id', $tenant->id)
            ->value('market_pack_version_id');

        DB::table('market_pack_versions')
            ->where('id', $versionId)
            ->update(['capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR)]);
    }

    private static function signedMessage(ChannelInboundMessage $message, string $secret): ChannelInboundMessage
    {
        return new ChannelInboundMessage(
            tenantId: $message->tenantId,
            channel: $message->channel,
            connectionKey: $message->connectionKey,
            externalMessageId: $message->externalMessageId,
            externalThreadReference: $message->externalThreadReference,
            identityType: $message->identityType,
            identityValue: $message->identityValue,
            content: $message->content,
            attachments: $message->attachments,
            consentStatus: $message->consentStatus,
            webhookEventId: $message->webhookEventId,
            webhookPayload: $message->webhookPayload,
            webhookSignature: hash_hmac('sha256', WebhookAuthenticityEnvelope::fromInbound($message)->canonicalBytes, $secret),
            webhookTimestamp: $message->webhookTimestamp,
            webhookReplayKey: $message->webhookReplayKey,
        );
    }
}
