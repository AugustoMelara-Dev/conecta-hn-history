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
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\WebChat\FakeWebChatAdapter;
use App\Infrastructure\Channels\Widget\FakeWidgetAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelContractTest extends TestCase
{
    use RefreshDatabase;

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
                ['name' => 'photo.jpg', 'mime_type' => 'image/jpeg'],
                ['name' => 'menu.pdf', 'mime_type' => 'application/pdf'],
            ],
            consentStatus: 'granted',
        ));

        self::assertSame('web_chat', $normalized->channel);
        self::assertSame('session-abc', $normalized->normalizedIdentity);
        self::assertSame(['image', 'document'], array_column($normalized->attachments, 'classification'));
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
        $signature = hash_hmac('sha256', $payload, 'email-webhook-secret');

        $normalized = $adapter->receive($context, new ChannelInboundMessage(
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
            webhookSignature: $signature,
            consentStatus: 'granted',
        ));

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
}
