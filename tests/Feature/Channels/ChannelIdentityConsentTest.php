<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelConsentService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelIdentityResolver;
use App\Application\Channels\ChannelInboundMessage;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelIdentityConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_and_phone_identities_are_normalized_per_tenant_and_do_not_leak(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenantA);
        GlobalMarketFixture::assignJapan($tenantB);

        $resolver = new ChannelIdentityResolver;

        $identityA = $resolver->resolve(
            $this->context($tenantA, 'email', 'email-a', 'CA', 'en-CA', 'CAD', 'America/Toronto'),
            new \App\Application\Channels\NormalizedInboundMessage(
                adapterKey: 'email.fake',
                tenantId: (string) $tenantA->id,
                channel: 'email',
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
            $this->context($tenantB, 'sms', 'sms-b', 'JP', 'ja-JP', 'JPY', 'Asia/Tokyo'),
            new \App\Application\Channels\NormalizedInboundMessage(
                adapterKey: 'sms.fail_closed',
                tenantId: (string) $tenantB->id,
                channel: 'sms',
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
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignJapan($tenant);
        $this->setMarketCapabilities($tenant, [
            'channels' => [
                'sms' => true,
            ],
        ]);

        $context = $this->context(
            $tenant,
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
        $adapter = new FailClosedSmsAdapter(credentialsConfigured: true);

        $normalized = $adapter->receive(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'sms',
            connectionKey: 'sms-primary',
            externalMessageId: 'sms-in-001',
            externalThreadReference: 'sms-thread-001',
            identityType: 'phone_e164',
            identityValue: ' 090-1234-5678 ',
            content: 'YES',
            consentStatus: 'granted',
        ));

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
}
