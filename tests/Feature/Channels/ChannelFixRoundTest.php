<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelConsentService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelDeliveryService;
use App\Application\Channels\ChannelIdentityResolver;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Application\Channels\ResolvedChannelIdentity;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Infrastructure\Channels\Voice\FailClosedVoiceAdapter;
use App\Infrastructure\Channels\WebChat\FakeWebChatAdapter;
use App\Infrastructure\Channels\WhatsApp\FailClosedWhatsAppAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelFixRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_mutation_requires_an_actor_authorized_for_the_context_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $foreignActor = User::factory()->create();
        Tenant::factory()->forUser($foreignActor)->create();

        $this->expectException(AuthorizationException::class);

        (new ChannelConnectionService)->connect($this->context($tenant, $foreignActor));
    }

    public function test_consent_mutation_requires_an_actor_authorized_for_the_context_tenant(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $foreignActor = User::factory()->create();
        Tenant::factory()->forUser($foreignActor)->create();
        $context = $this->context($tenant, $foreignActor, 'email', 'email-local');
        $normalized = $this->normalized($tenant, 'email', 'email-local');
        $identity = new ResolvedChannelIdentity(
            id: (string) fake()->uuid(),
            tenantId: (string) $tenant->id,
            channel: 'email',
            type: 'email',
            normalizedValue: 'buyer@example.test',
        );

        $this->expectException(AuthorizationException::class);

        (new ChannelConsentService)->record($context, $identity, $normalized, 'messaging');
    }

    public function test_delivery_mutation_rejects_a_message_from_another_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $foreignTenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($foreignTenant);
        $context = $this->context($tenant, $owner, 'widget', 'widget-local');
        $message = new ChannelOutboundMessage(
            tenantId: (string) $foreignTenant->id,
            channel: 'widget',
            connectionKey: 'widget-local',
            idempotencyKey: 'cross-tenant-delivery',
            externalThreadReference: 'thread-001',
            recipient: 'visitor:001',
            content: 'must not send',
        );

        $this->expectException(InvalidArgumentException::class);

        (new ChannelDeliveryService(new ChannelConnectionService))->send(
            new FakeWebChatAdapter,
            $context,
            $message,
        );
    }

    public function test_identity_mutation_rejects_a_normalized_message_from_another_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $foreignTenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($foreignTenant);

        $this->expectException(InvalidArgumentException::class);

        (new ChannelIdentityResolver)->resolve(
            $this->context($tenant, $owner, 'email', 'email-local'),
            $this->normalized($foreignTenant, 'email', 'email-local'),
        );
    }

    public function test_webhook_claim_rejects_a_normalized_message_from_another_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $foreignTenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($foreignTenant);

        $this->expectException(InvalidArgumentException::class);

        (new ChannelConnectionService)->claimWebhook(
            $this->context($tenant, $owner, 'email', 'email-local'),
            $this->normalized($foreignTenant, 'email', 'email-local'),
        );
    }

    public function test_consent_mutation_rejects_an_identity_from_another_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $foreignTenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($foreignTenant);
        $normalized = $this->normalized($tenant, 'email', 'email-local');
        $foreignIdentity = new ResolvedChannelIdentity(
            id: (string) fake()->uuid(),
            tenantId: (string) $foreignTenant->id,
            channel: 'email',
            type: 'email',
            normalizedValue: 'foreign@example.test',
        );

        $this->expectException(InvalidArgumentException::class);

        (new ChannelConsentService)->record(
            $this->context($tenant, $owner, 'email', 'email-local'),
            $foreignIdentity,
            $normalized,
            'messaging',
        );
    }

    public function test_delivery_persists_pending_intent_before_side_effect_and_retries_with_one_stable_provider_key(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $context = $this->context($tenant, $owner, 'widget', 'widget-local');
        $adapter = new class implements ChannelAdapterInterface
        {
            /** @var list<array{state:string|null,key:string|null}> */
            public array $observations = [];

            public int $providerSideEffects = 0;

            private bool $crashAfterAcceptance = true;

            /** @var array<string, DeliveryReceipt> */
            private array $accepted = [];

            public function capability(ChannelContext $context): ChannelCapabilityDecision
            {
                return ChannelCapabilityDecision::allow('test.provider', 'test.provider');
            }

            public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
            {
                throw new \LogicException('Inbound is not part of this delivery test.');
            }

            public function send(ChannelOutboundMessage $message): DeliveryReceipt
            {
                $key = $message->providerIdempotencyKey;
                $this->observations[] = [
                    'state' => DB::table('channel_delivery_outbox')
                        ->where('tenant_id', $message->tenantId)
                        ->where('idempotency_key', $message->idempotencyKey)
                        ->value('delivery_state'),
                    'key' => $key,
                ];

                if (! isset($this->accepted[$key])) {
                    $this->accepted[$key] = new DeliveryReceipt(
                        tenantId: $message->tenantId,
                        channel: $message->channel,
                        connectionKey: $message->connectionKey,
                        idempotencyKey: $message->idempotencyKey,
                        providerMessageId: 'provider-message-001',
                        providerEventId: 'provider-event-001',
                        deliveryState: 'delivered',
                        costAmount: '0.00',
                        costCurrency: 'CAD',
                        providerIdempotencyKey: $key,
                    );
                    $this->providerSideEffects++;

                    if ($this->crashAfterAcceptance) {
                        $this->crashAfterAcceptance = false;
                        throw new RuntimeException('crash after provider acceptance');
                    }
                }

                return $this->accepted[$key];
            }
        };
        $message = new ChannelOutboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'widget',
            connectionKey: 'widget-local',
            idempotencyKey: 'durable-delivery-001',
            externalThreadReference: 'thread-001',
            recipient: 'visitor:001',
            content: 'durable message',
        );
        $delivery = new ChannelDeliveryService(new ChannelConnectionService);

        $first = $delivery->send($adapter, $context, $message);
        $second = $delivery->send($adapter, $context, $message);

        self::assertSame('provider_unavailable', $first->deliveryState);
        self::assertSame('delivered', $second->deliveryState);
        self::assertNotEmpty($first->providerIdempotencyKey);
        self::assertSame($first->providerIdempotencyKey, $second->providerIdempotencyKey);
        self::assertSame(['pending', 'pending'], array_column($adapter->observations, 'state'));
        self::assertSame($adapter->observations[0]['key'], $adapter->observations[1]['key']);
        self::assertSame(1, $adapter->providerSideEffects);
        self::assertSame(2, DB::table('channel_delivery_outbox')->value('attempt_count'));
        self::assertSame('delivered', DB::table('channel_delivery_outbox')->value('delivery_state'));
        self::assertSame(1, DB::table('channel_delivery_attempts')->count());
        self::assertSame(
            $first->providerIdempotencyKey,
            DB::table('channel_delivery_outbox')->value('provider_idempotency_key'),
        );
    }

    public function test_inbound_fake_uses_the_supplied_market_context(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $context = $this->context(
            $tenant,
            $owner,
            'web_chat',
            'web-chat-local',
            'JP',
            'ja-JP',
            'JPY',
            'Asia/Tokyo',
        );

        $normalized = (new FakeWebChatAdapter)->receive(
            $context,
            new ChannelInboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'web_chat',
                connectionKey: 'web-chat-local',
                externalMessageId: 'inbound-context-001',
                externalThreadReference: 'thread-001',
                identityType: 'anonymous_session',
                identityValue: 'session-001',
                content: 'こんにちは',
            ),
        );

        self::assertSame('JP', $normalized->marketCountryCode);
        self::assertSame('ja-JP', $normalized->locale);
        self::assertSame('JPY', $normalized->costCurrency);
        self::assertSame('Asia/Tokyo', $normalized->timezone);
    }

    public function test_channel_context_rejects_placeholder_market_values(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->expectException(InvalidArgumentException::class);

        $this->context($tenant, $owner, 'web_chat', 'web-chat-local', 'ZZ', 'und', 'USD', 'UTC');
    }

    public function test_normalized_inbound_contract_rejects_placeholder_market_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NormalizedInboundMessage(
            adapterKey: 'test.fake',
            tenantId: (string) fake()->uuid(),
            channel: 'web_chat',
            connectionKey: 'web-chat-local',
            externalMessageId: 'inbound-placeholder-001',
            externalThreadReference: null,
            normalizedIdentity: 'session-001',
            identityType: 'anonymous_session',
            content: 'placeholder context must fail',
            attachments: [],
            consentStatus: null,
            deliveryState: 'delivered',
            providerEventId: 'event-001',
            costAmount: '0.00',
            costCurrency: 'USD',
            marketCountryCode: 'ZZ',
            locale: 'und',
            timezone: 'UTC',
        );
    }

    public function test_whatsapp_sms_and_voice_inbound_fail_closed_without_webhook_authenticity(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $adapters = [
            'whatsapp' => [
                'adapter' => new FailClosedWhatsAppAdapter(webhookSecret: 'channel-secret'),
                'connection' => 'whatsapp-local',
            ],
            'sms' => [
                'adapter' => new FailClosedSmsAdapter(webhookSecret: 'channel-secret'),
                'connection' => 'sms-local',
            ],
            'voice' => [
                'adapter' => new FailClosedVoiceAdapter(webhookSecret: 'channel-secret'),
                'connection' => 'voice-local',
            ],
        ];

        foreach ($adapters as $channel => $definition) {
            try {
                $definition['adapter']->receive(
                    $this->context($tenant, $owner, $channel, $definition['connection']),
                    new ChannelInboundMessage(
                        tenantId: (string) $tenant->id,
                        channel: $channel,
                        connectionKey: $definition['connection'],
                        externalMessageId: $channel.'-inbound-001',
                        externalThreadReference: $channel.'-thread-001',
                        identityType: 'phone_e164',
                        identityValue: '+15551234567',
                        content: 'inbound without signature',
                    ),
                );
                self::fail($channel.' inbound without webhook authenticity was accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('authenticity', strtolower($exception->getMessage()), $channel);
            }
        }
    }

    public function test_channel_config_declares_durability_authenticity_data_and_health_contracts(): void
    {
        $config = require dirname(__DIR__, 3).'/config/dovra_channels.php';

        foreach (['retry', 'idempotency', 'webhook_verification', 'data_classification', 'retention', 'health'] as $key) {
            self::assertArrayHasKey($key, $config['contract'] ?? [], $key);
        }

        self::assertFalse($config['contract']['idempotency']['exactly_once'] ?? true);
        self::assertContains('pending', $config['contract']['retry']['durable_states'] ?? []);
        self::assertSame('channel_webhook_claims', $config['contract']['webhook_verification']['replay_claim_table'] ?? null);
    }

    /** @return array{0:Tenant,1:User} */
    private function tenantWithOwner(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        GlobalMarketFixture::assignCanada($tenant);

        return [$tenant, $owner];
    }

    private function context(
        Tenant $tenant,
        User $actor,
        string $channel = 'widget',
        string $connectionKey = 'widget-local',
        string $country = 'CA',
        string $locale = 'en-CA',
        string $currency = 'CAD',
        string $timezone = 'America/Toronto',
    ): ChannelContext {
        return new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: $connectionKey,
            marketCountryCode: $country,
            locale: $locale,
            currency: $currency,
            timezone: $timezone,
            marketCapabilities: [],
            connection: [],
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
    }

    private function normalized(Tenant $tenant, string $channel, string $connectionKey): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: $channel.'.fake',
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: $connectionKey,
            externalMessageId: $channel.'-inbound-001',
            externalThreadReference: $channel.'-thread-001',
            normalizedIdentity: 'buyer@example.test',
            identityType: 'email',
            content: 'hello',
            attachments: [],
            consentStatus: 'granted',
            deliveryState: 'delivered',
            providerEventId: $channel.'-event-001',
            costAmount: '0.00',
            costCurrency: 'CAD',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            timezone: 'America/Toronto',
        );
    }
}
