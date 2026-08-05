<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\Support\WebhookAuthenticator;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelWebhookReplayTest extends TestCase
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

    public function test_signed_webhook_uses_the_versioned_length_safe_envelope(): void
    {
        $message = new ChannelInboundMessage(
            tenantId: '00000000-0000-4000-8000-000000000001',
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-vector',
            externalThreadReference: null,
            identityType: 'email',
            identityValue: 'vector@example.test',
            content: 'vector',
            webhookEventId: 'evt|vector',
            webhookPayload: "{\"event\":\"message.received\",\"raw\":\"a|b\"}\n",
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'replay|vector',
        );

        $envelope = WebhookAuthenticityEnvelope::fromInbound($message);

        self::assertSame(
            '0000000776657273696f6e00000018646f7672612d6368616e6e656c2d776562686f6f6b2d76310000000974656e616e745f69640000002430303030303030302d303030302d343030302d383030302d303030303030303030303031000000076368616e6e656c00000005656d61696c0000000e636f6e6e656374696f6e5f6b65790000000b656d61696c2d6c6f63616c000000086576656e745f69640000000a6576747c766563746f720000000974696d657374616d7000000014323032362d30382d30335431353a30303a30305a0000000a7265706c61795f6b65790000000d7265706c61797c766563746f720000000e7061796c6f61645f7368613235360000004033333638313266353866346161663861656231623239316561373332666233376566633134363931316461343330353236653638373030313039383838643866',
            bin2hex($envelope->canonicalBytes),
        );
        self::assertSame('336812f58f4aaf8aeb1b291ea732fb37efc146911da430526e68700109888d8f', $envelope->payloadHash);
        self::assertSame('bea696b066979c9a9fea1c0983147f0933843ccc3b998e52f72a4aac63be7c96', $envelope->claimHash);
        self::assertSame('8362ae102a8133a2e95801a918a2b27b0c6cb6ec7d944fcc7386c84a3f24f3ee', $envelope->replayProofHash);
        self::assertSame(64, strlen($envelope->payloadHash));
        self::assertSame(64, strlen($envelope->claimHash));
        self::assertSame(64, strlen($envelope->replayProofHash));
    }

    public function test_signed_email_webhook_claims_once_and_rejects_replay(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
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
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $connectionService = new ChannelConnectionService;
        $connectionService->connect($context);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $payload = json_encode(['event' => 'message.received', 'id' => 'mail-evt-100'], JSON_THROW_ON_ERROR);

        $normalized = $adapter->receive($context, self::signedMessage(new ChannelInboundMessage(
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
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-replay-100',
        ), 'email-webhook-secret'));

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

    public function test_signed_webhook_freshness_boundaries_are_inclusive_and_strict(): void
    {
        self::assertSame('2026-08-03T14:55:00Z', CarbonImmutable::now('UTC')->startOfSecond()->subSeconds(300)->format('Y-m-d\\TH:i:s\\Z'));
        self::assertSame(300, config('dovra_channels.contract.webhook_verification.max_age_seconds'));
        foreach (['2026-08-03T14:55:00Z', '2026-08-03T15:00:30Z'] as $timestamp) {
            $message = new ChannelInboundMessage(
                tenantId: '00000000-0000-4000-8000-000000000001',
                channel: 'email',
                connectionKey: 'email-local',
                externalMessageId: 'mail-boundary-'.$timestamp,
                externalThreadReference: null,
                identityType: 'email',
                identityValue: 'boundary@example.test',
                content: 'boundary',
                webhookEventId: 'mail-boundary-'.$timestamp,
                webhookPayload: '{"boundary":true}',
                webhookTimestamp: $timestamp,
                webhookReplayKey: 'replay-boundary-'.$timestamp,
            );

            WebhookAuthenticator::assertSigned(self::signedMessage($message, 'email-webhook-secret'), 'email-webhook-secret');
        }

        foreach (['2026-08-03T14:54:59Z', '2026-08-03T15:00:31Z', '2026-08-03T15:00:00.000Z', '2026-08-03T15:00:00+00:00', '2026-08-03 15:00:00Z', 'not-a-timestamp'] as $timestamp) {
            $message = new ChannelInboundMessage(
                tenantId: '00000000-0000-4000-8000-000000000001',
                channel: 'email',
                connectionKey: 'email-local',
                externalMessageId: 'mail-invalid-boundary-'.$timestamp,
                externalThreadReference: null,
                identityType: 'email',
                identityValue: 'boundary@example.test',
                content: 'boundary',
                webhookEventId: 'mail-invalid-boundary-'.$timestamp,
                webhookPayload: '{"boundary":true}',
                webhookTimestamp: $timestamp,
                webhookReplayKey: 'replay-invalid-boundary-'.$timestamp,
            );

            $before = self::protectedTableStateHash();
            try {
                WebhookAuthenticator::assertSigned(self::signedMessage($message, 'email-webhook-secret'), 'email-webhook-secret');
                self::fail('Expected timestamp to be rejected: '.$timestamp);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage());
            }
            self::assertSame($before, self::protectedTableStateHash(), 'Timestamp rejection wrote protected rows: '.$timestamp);
        }
    }

    public function test_invalid_webhook_verification_config_fails_closed_without_writes(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $message = self::signedMessage(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-invalid-config',
            externalThreadReference: 'thread@example.test',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'invalid config',
            webhookEventId: 'mail-invalid-config-event',
            webhookPayload: '{"config":true}',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-invalid-config-replay',
        ), 'email-webhook-secret');
        $baseConfig = config('dovra_channels.contract.webhook_verification');
        $cases = [
            'algorithm' => 'hmac-sha512',
            'signature_version' => 'legacy-v0',
            'timestamp_format' => 'Y-m-d H:i:s',
            'max_age_seconds_type' => '300',
            'max_age_seconds_low' => 0,
            'future_tolerance_seconds_type' => '30',
            'future_tolerance_seconds_low' => -1,
        ];

        foreach ($cases as $case => $value) {
            $config = $baseConfig;
            if (str_ends_with($case, '_type')) {
                $config[str_replace('_type', '', $case)] = $value;
            } elseif (str_ends_with($case, '_low')) {
                $config[str_replace('_low', '', $case)] = $value;
            } else {
                $config[$case] = $value;
            }
            Config::set('dovra_channels.contract.webhook_verification', $config);
            $before = self::protectedTableStateHash();
            try {
                $adapter->receive($context, $message);
                self::fail('Expected invalid webhook verification config to fail: '.$case);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage(), $case);
            } finally {
                Config::set('dovra_channels.contract.webhook_verification', $baseConfig);
            }
            self::assertSame($before, self::protectedTableStateHash(), 'Invalid config wrote protected rows: '.$case);
        }
    }

    public function test_captured_signature_rejects_each_bound_field_without_persistence(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $original = self::signedMessage(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-mutation',
            externalThreadReference: 'thread@example.test',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'mutation',
            webhookEventId: 'mail-mutation-event',
            webhookPayload: '{"mutation":false}',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-mutation-replay',
        ), 'email-webhook-secret');
        $mutations = [
            'tenantId' => '00000000-0000-4000-8000-000000000002',
            'channel' => 'email-alt',
            'connectionKey' => 'email-alt',
            'webhookEventId' => 'mail-mutation-event-alt',
            'webhookTimestamp' => '2026-08-03T15:00:01Z',
            'webhookReplayKey' => 'mail-mutation-replay-alt',
            'webhookPayload' => '{"mutation":true}',
        ];

        foreach ($mutations as $field => $value) {
            $before = self::protectedTableStateHash();
            try {
                $adapter->receive($context, self::mutatedMessage($original, [$field => $value]));
                self::fail('Expected captured signature mutation to be rejected: '.$field);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage());
            }
            self::assertSame($before, self::protectedTableStateHash(), 'Mutation wrote protected rows: '.$field);
        }
    }

    public function test_signed_claim_hash_controls_early_replay_and_orphan_repair(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        app(\App\Domain\Shared\Services\TenantContext::class)->setTenant((string) $tenant->id);
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $original = new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-bridge',
            externalThreadReference: 'thread@example.test',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'bridge',
            consentStatus: 'granted',
            webhookEventId: 'mail-bridge-event',
            webhookPayload: '{"event":"mail-bridge-event","version":1}',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-bridge-replay-1',
        );
        $normalized = $adapter->receive($context, self::signedMessage($original, 'email-webhook-secret'));
        $bridge = app(ConversationChannelBridge::class);
        $conversationId = $bridge->resolveOrCreate((string) $tenant->id, $context, $normalized);
        $claimHash = (string) $normalized->metadata[WebhookAuthenticityEnvelope::META_CLAIM_HASH];
        self::assertSame($claimHash, (string) DB::table('channel_webhook_claims')->value('payload_hash'));
        self::assertSame($claimHash, (string) DB::table('dovra_conversation_channel_links')->value('payload_hash'));

        $changedEnvelope = self::mutatedMessage($original, [
            'webhookTimestamp' => '2026-08-03T15:00:01Z',
            'webhookReplayKey' => 'mail-bridge-replay-2',
            'webhookPayload' => '{"event":"mail-bridge-event","version":2}',
        ]);
        $changed = $adapter->receive($context, self::signedMessage($changedEnvelope, 'email-webhook-secret'));
        $beforeEarlyConflict = self::protectedTableStateHash();
        try {
            $bridge->resolveOrCreate((string) $tenant->id, $context, $changed);
            self::fail('Expected changed authenticated envelope to conflict on early replay.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        self::assertSame($beforeEarlyConflict, self::protectedTableStateHash());

        DB::table('dovra_conversation_channel_links')->where('provider_event_id', 'mail-bridge-event')->delete();
        $repaired = $bridge->resolveOrCreate((string) $tenant->id, $context, $normalized);
        self::assertSame($conversationId, $repaired);
        self::assertSame($claimHash, (string) DB::table('dovra_conversation_channel_links')->value('payload_hash'));

        DB::table('dovra_conversation_channel_links')->where('provider_event_id', 'mail-bridge-event')->delete();
        $beforeRepairConflict = self::protectedTableStateHash();
        try {
            $bridge->resolveOrCreate((string) $tenant->id, $context, $changed);
            self::fail('Expected changed authenticated envelope to conflict during orphan repair.');
        } catch (RuntimeException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        self::assertSame($beforeRepairConflict, self::protectedTableStateHash());
    }

    public function test_bad_hmac_fails_closed_before_claiming_without_writes(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);

        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );

        $before = self::protectedTableStateHash();
        try {
            $adapter->receive($context, new ChannelInboundMessage(
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
            webhookSignature: str_repeat('0', 64),
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'mail-replay-101',
            ));
            self::fail('Expected bad HMAC to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage());
        }

        self::assertSame($before, self::protectedTableStateHash());
    }

    public function test_malformed_webhook_signature_fails_closed_before_claiming_without_writes(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $before = self::protectedTableStateHash();
        try {
            $adapter->receive($context, new ChannelInboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'email',
                connectionKey: 'email-local',
                externalMessageId: 'mail-in-malformed-signature',
                externalThreadReference: 'reply-thread@example.test',
                identityType: 'email',
                identityValue: 'reply@example.test',
                content: 'received',
                webhookEventId: 'mail-evt-malformed-signature',
                webhookPayload: '{"event":"message.received"}',
                webhookSignature: 'bad-signature',
                webhookTimestamp: '2026-08-03T15:00:00Z',
                webhookReplayKey: 'mail-replay-malformed-signature',
            ));
            self::fail('Expected malformed signature to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage());
        }

        self::assertSame($before, self::protectedTableStateHash());
    }

    public function test_email_webhook_event_without_signature_fails_closed_before_claiming(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );

        $before = self::protectedTableStateHash();
        try {
            $adapter->receive($context, new ChannelInboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'email',
                connectionKey: 'email-local',
                externalMessageId: 'mail-in-102',
                externalThreadReference: 'reply-thread@example.test',
                identityType: 'email',
                identityValue: 'reply@example.test',
                content: 'received',
                webhookEventId: 'mail-evt-102',
            ));
            self::fail('Expected missing signature to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel webhook authenticity is required.', $exception->getMessage());
        }
        self::assertSame($before, self::protectedTableStateHash());
    }

    public function test_email_webhook_rejects_missing_signature_event_timestamp_and_replay_fields_before_identity_normalization(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $adapter = new FakeEmailAdapter('email-webhook-secret');
        $context = new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope(
                tenantId: (string) $tenant->id,
                actorId: (string) $actor->id,
            ),
        );
        $payload = '{"event":"message.received","id":"mail-evt-103"}';
        $valid = [
            'webhookEventId' => 'mail-evt-103',
            'webhookPayload' => $payload,
            'webhookSignature' => null,
            'webhookTimestamp' => '2026-08-03T15:00:00Z',
            'webhookReplayKey' => 'mail-replay-103',
        ];
        $valid['webhookSignature'] = self::signature(new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'mail-in-103',
            externalThreadReference: 'reply-thread@example.test',
            identityType: 'email',
            identityValue: 'reply@example.test',
            content: 'received',
            webhookEventId: $valid['webhookEventId'],
            webhookPayload: $valid['webhookPayload'],
            webhookTimestamp: $valid['webhookTimestamp'],
            webhookReplayKey: $valid['webhookReplayKey'],
        ), 'email-webhook-secret');

        foreach (['webhookSignature', 'webhookEventId', 'webhookTimestamp', 'webhookReplayKey'] as $missingField) {
            $fields = $valid;
            $fields[$missingField] = null;

            $before = self::protectedTableStateHash();
            try {
                $adapter->receive($context, new ChannelInboundMessage(
                    tenantId: (string) $tenant->id,
                    channel: 'email',
                    connectionKey: 'email-local',
                    externalMessageId: 'mail-in-103-'.$missingField,
                    externalThreadReference: 'reply-thread@example.test',
                    identityType: 'email',
                    identityValue: 'reply@example.test',
                    content: 'received',
                    webhookEventId: $fields['webhookEventId'],
                    webhookPayload: $fields['webhookPayload'],
                    webhookSignature: $fields['webhookSignature'],
                    webhookTimestamp: $fields['webhookTimestamp'],
                    webhookReplayKey: $fields['webhookReplayKey'],
                ));
                self::fail('Expected missing webhook field to be rejected: '.$missingField);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Channel webhook authenticity is required.', $exception->getMessage());
            }
            self::assertSame($before, self::protectedTableStateHash(), 'Missing field wrote protected rows: '.$missingField);
        }
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
            webhookSignature: self::signature($message, $secret),
            webhookTimestamp: $message->webhookTimestamp,
            webhookReplayKey: $message->webhookReplayKey,
        );
    }

    private static function signature(ChannelInboundMessage $message, string $secret): string
    {
        return hash_hmac('sha256', WebhookAuthenticityEnvelope::fromInbound($message)->canonicalBytes, $secret);
    }

    /** @param array<string, string> $overrides */
    private static function mutatedMessage(ChannelInboundMessage $message, array $overrides): ChannelInboundMessage
    {
        return new ChannelInboundMessage(
            tenantId: $overrides['tenantId'] ?? $message->tenantId,
            channel: $overrides['channel'] ?? $message->channel,
            connectionKey: $overrides['connectionKey'] ?? $message->connectionKey,
            externalMessageId: $message->externalMessageId,
            externalThreadReference: $message->externalThreadReference,
            identityType: $message->identityType,
            identityValue: $message->identityValue,
            content: $message->content,
            attachments: $message->attachments,
            consentStatus: $message->consentStatus,
            webhookEventId: $overrides['webhookEventId'] ?? $message->webhookEventId,
            webhookPayload: $overrides['webhookPayload'] ?? $message->webhookPayload,
            webhookSignature: $message->webhookSignature,
            webhookTimestamp: $overrides['webhookTimestamp'] ?? $message->webhookTimestamp,
            webhookReplayKey: $overrides['webhookReplayKey'] ?? $message->webhookReplayKey,
        );
    }

    private static function protectedTableStateHash(): string
    {
        $tables = [
            'channel_webhook_claims',
            'channel_identities',
            'channel_consents',
            'dovra_conversation_channel_links',
            'ai_conversations',
            'ai_messages',
        ];
        $state = [];
        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(static fn (object $row): array => (array) $row)->all();
            usort($rows, static fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));
            $state[$table] = $rows;
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
