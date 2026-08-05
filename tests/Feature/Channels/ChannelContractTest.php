<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelDeliveryService;
use App\Application\Channels\ChannelIdentityResolver;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\NormalizedInboundMessage;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\Support\ChannelInboundNormalizer;
use App\Infrastructure\Channels\WebChat\FakeWebChatAdapter;
use App\Infrastructure\Channels\Widget\FakeWidgetAdapter;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelContractTest extends TestCase
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

    public function test_web_chat_fake_normalizes_anonymous_identity_and_classifies_attachments(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);

        $context = $this->context($tenant, $actor, 'web_chat', 'web-chat-local');
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $adapter = new FakeWebChatAdapter;

        $normalized = $adapter->receive($context, new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'web_chat',
            connectionKey: 'web-chat-local',
            externalMessageId: 'web-in-001',
            externalThreadReference: 'visitor-thread-001',
            identityType: 'anonymous_session',
            identityValue: ' Session-ABC ',
            content: 'Need availability for tomorrow.',
            attachments: [
                [
                    'name' => 'photo.jpg',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 120,
                    'url' => 'https://provider.invalid/photo.jpg',
                    'token' => 'opaque-token',
                ],
                [
                    'name' => 'menu.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 240,
                    'body' => 'raw-provider-body',
                ],
            ],
            consentStatus: 'granted',
        ));

        self::assertSame('web_chat', $normalized->channel);
        self::assertSame('session-abc', $normalized->normalizedIdentity);
        self::assertSame([
            [
                'name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => 120,
                'classification' => 'image',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ],
            [
                'name' => 'menu.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 240,
                'classification' => 'document',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ],
        ], $normalized->attachments);
        self::assertSame('delivered', $normalized->deliveryState);

        $identity = (new ChannelIdentityResolver)->resolve($context, $normalized);

        self::assertSame('anonymous_session', $identity->type);
        self::assertSame('session-abc', $identity->normalizedValue);
        $this->assertDatabaseHas('channel_identities', [
            'tenant_id' => $tenant->id,
            'channel' => 'web_chat',
            'identity_type' => 'anonymous_session',
            'normalized_value' => 'session-abc',
        ]);
    }

    public function test_widget_fake_delivery_is_idempotent_and_cost_free(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);

        $context = $this->context($tenant, $actor, 'widget', 'widget-local');
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $adapter = new FakeWidgetAdapter;
        $delivery = new ChannelDeliveryService($connectionService);

        $message = new ChannelOutboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'widget',
            connectionKey: 'widget-local',
            idempotencyKey: 'widget-out-001',
            externalThreadReference: 'widget-thread-001',
            recipient: 'visitor:widget-001',
            content: 'The team can help you at 9:00 AM.',
        );

        $first = $delivery->send($adapter, $context, $message);
        $second = $delivery->send($adapter, $context, $message);

        self::assertSame($first->providerMessageId, $second->providerMessageId);
        self::assertSame('delivered', $first->deliveryState);
        self::assertSame('USD', $first->costCurrency);
        self::assertSame('0.00', $first->costAmount);
        self::assertSame(1, DB::table('channel_delivery_attempts')->count());
        $this->assertDatabaseHas('channel_delivery_attempts', [
            'tenant_id' => $tenant->id,
            'channel' => 'widget',
            'idempotency_key' => 'widget-out-001',
            'delivery_state' => 'delivered',
        ]);
    }

    public function test_email_fake_verifies_signature_normalizes_address_and_tracks_cost(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);

        $context = $this->context($tenant, $actor, 'email', 'email-local', ['secret_reference_key' => 'email.fake']);
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $payload = json_encode(['message' => 'Reply by email', 'event' => 'mail-evt-001'], JSON_THROW_ON_ERROR);

        $normalized = $adapter->receive($context, self::signedMessage(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-001',
            externalThreadReference: 'thread@example.test',
            identityType: 'email',
            identityValue: ' Person+Sales@Example.TEST ',
            content: 'Can you send the invoice?',
            webhookEventId: 'mail-evt-001',
            webhookPayload: $payload,
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-replay-001',
            consentStatus: 'granted',
        ), 'email-webhook-secret'));

        self::assertSame('person+sales@example.test', $normalized->normalizedIdentity);
        self::assertTrue($connectionService->claimWebhook($context, $normalized));

        $identity = (new ChannelIdentityResolver)->resolve($context, $normalized);
        self::assertSame('person+sales@example.test', $identity->normalizedValue);

        $receipt = (new ChannelDeliveryService($connectionService))->send(
            $adapter,
            $context,
            new ChannelOutboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'email',
                connectionKey: 'email-local',
                idempotencyKey: 'email-out-001',
                externalThreadReference: 'thread@example.test',
                recipient: 'person+sales@example.test',
                subject: 'Invoice',
                content: 'Attached is your invoice.',
            ),
        );

        self::assertSame('delivered', $receipt->deliveryState);
        self::assertSame('USD', $receipt->costCurrency);
        self::assertSame('0.01', $receipt->costAmount);
        $this->assertDatabaseHas('channel_delivery_attempts', [
            'tenant_id' => $tenant->id,
            'channel' => 'email',
            'idempotency_key' => 'email-out-001',
            'cost_amount' => '0.01',
        ]);
    }

    public function test_signed_normalization_protects_envelope_metadata_and_claim_persistence(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = $this->context($tenant, $actor, 'email', 'email-local');
        $payload = "{\"raw\":true}\n";
        $unsigned = new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-protected',
            externalThreadReference: 'thread@example.test',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'protected',
            webhookEventId: 'mail-event-protected',
            webhookPayload: $payload,
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-replay-protected',
        );
        $signed = self::signedMessage($unsigned, 'email-webhook-secret');
        $normalized = ChannelInboundNormalizer::normalize(
            adapterKey: 'email.fake',
            context: $context,
            message: $signed,
            attachments: [],
            metadata: [
                'custom' => 'preserved',
                WebhookAuthenticityEnvelope::META_SIGNATURE_VERSION => 'forged-version',
                WebhookAuthenticityEnvelope::META_PAYLOAD_HASH => str_repeat('b', 64),
                WebhookAuthenticityEnvelope::META_CLAIM_HASH => str_repeat('c', 64),
                WebhookAuthenticityEnvelope::META_REPLAY_PROOF_HASH => str_repeat('d', 64),
            ],
        );
        $envelope = WebhookAuthenticityEnvelope::fromInbound($signed);

        self::assertSame('preserved', $normalized->metadata['custom']);
        self::assertSame(WebhookAuthenticityEnvelope::VERSION, $normalized->metadata[WebhookAuthenticityEnvelope::META_SIGNATURE_VERSION]);
        self::assertSame($envelope->payloadHash, $normalized->metadata[WebhookAuthenticityEnvelope::META_PAYLOAD_HASH]);
        self::assertSame($envelope->claimHash, $normalized->metadata[WebhookAuthenticityEnvelope::META_CLAIM_HASH]);
        self::assertSame($envelope->replayProofHash, $normalized->metadata[WebhookAuthenticityEnvelope::META_REPLAY_PROOF_HASH]);

        $connectionService = new ChannelConnectionService;
        self::assertTrue($connectionService->claimWebhook($context, $normalized));
        self::assertSame($envelope->claimHash, (string) DB::table('channel_webhook_claims')->value('payload_hash'));
    }

    public function test_partial_protected_metadata_cannot_downgrade_to_unsigned(): void
    {
        $normalized = new NormalizedInboundMessage(
            adapterKey: 'email.fake',
            tenantId: '00000000-0000-4000-8000-000000000001',
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-partial',
            externalThreadReference: null,
            normalizedIdentity: 'buyer@example.test',
            identityType: 'email',
            content: 'partial',
            attachments: [],
            consentStatus: null,
            deliveryState: 'delivered',
            providerEventId: 'mail-partial-event',
            costAmount: '0.00',
            costCurrency: 'CAD',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            timezone: 'America/Toronto',
            metadata: [WebhookAuthenticityEnvelope::META_CLAIM_HASH => str_repeat('a', 64)],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel webhook authenticity metadata is invalid.');
        WebhookAuthenticityEnvelope::authenticatedClaimHash($normalized);
    }

    private function context(Tenant $tenant, User $actor, string $channel, string $connectionKey, array $connection = []): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: $connectionKey,
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            marketCapabilities: [],
            connection: $connection,
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
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
