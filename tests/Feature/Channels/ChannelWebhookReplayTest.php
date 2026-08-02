<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelWebhookReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_email_webhook_claims_once_and_rejects_replay(): void
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);

        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            marketCapabilities: [],
            connection: ['secret_reference_key' => 'email.fake'],
        );
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $payload = json_encode(['event' => 'message.received', 'id' => 'mail-evt-100'], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'email-webhook-secret');

        $normalized = $adapter->receive(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-100',
            externalThreadReference: 'reply-thread@example.test',
            identityType: 'email',
            identityValue: 'reply@example.test',
            content: 'received',
            webhookEventId: 'mail-evt-100',
            webhookPayload: $payload,
            webhookSignature: $signature,
        ));

        self::assertTrue($connectionService->claimWebhook($context, $normalized));
        self::assertFalse($connectionService->claimWebhook($context, $normalized));
        $this->assertDatabaseCount('channel_webhook_claims', 1);
        $this->assertDatabaseHas('channel_webhook_claims', [
            'tenant_id' => $tenant->id,
            'channel' => 'email',
            'provider_event_id' => 'mail-evt-100',
            'connection_key' => 'email-local',
        ]);
    }

    public function test_invalid_webhook_signature_fails_closed_before_claiming(): void
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);

        $adapter = new FakeEmailAdapter('email-webhook-secret');

        $this->expectException(InvalidArgumentException::class);

        $adapter->receive(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-101',
            externalThreadReference: 'reply-thread@example.test',
            identityType: 'email',
            identityValue: 'reply@example.test',
            content: 'received',
            webhookEventId: 'mail-evt-101',
            webhookPayload: '{"event":"message.received","id":"mail-evt-101"}',
            webhookSignature: 'bad-signature',
        ));

        self::assertSame(0, DB::table('channel_webhook_claims')->count());
    }
}
