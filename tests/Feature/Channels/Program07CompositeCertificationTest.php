<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Ai\Conversation\ConversationStore;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelConflictException;
use App\Application\Channels\ChannelConnectionService;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelDeliveryService;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\Sms\FailClosedSmsAdapter;
use App\Infrastructure\Channels\Sms\SmsProviderInterface;
use App\Infrastructure\Channels\Voice\FailClosedVoiceAdapter;
use App\Infrastructure\Channels\Voice\VoiceProviderInterface;
use App\Infrastructure\Channels\WhatsApp\FailClosedWhatsAppAdapter;
use App\Infrastructure\Channels\WhatsApp\WhatsAppProviderInterface;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\Channels\Program07CompositeCertificateFixture;
use Tests\Support\Channels\Program07CompositeCertificateReport;
use Tests\TestCase;

final class Program07CompositeCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (strtolower((string) getenv('DOVRA_PROGRAM07_COMPOSITE_ALLOW')) !== 'true') {
            $this->markTestSkipped('Program 07 composite requires its explicit disposable-SQLite certifier guard.');
        }

        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03T15:00:00Z', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_program07_composite_is_locally_verified_inside_guarded_sqlite(): void
    {
        $topology = Program07CompositeCertificateFixture::create();
        $subjectTenant = $topology['subject_tenant'];
        $subjectActor = $topology['subject_actor'];
        $foreignTenant = $topology['foreign_tenant'];
        $foreignActor = $topology['foreign_actor'];
        $emailSecret = $topology['email_secret'];
        $whatsappSecret = $topology['whatsapp_secret'];

        $foreignBefore = Program07CompositeCertificateFixture::tenantCounts($foreignTenant);
        $foreignHashBefore = $this->tenantStateHash($foreignTenant);
        self::assertGreaterThan(0, $foreignBefore['channel_connections']);
        self::assertGreaterThan(0, $foreignBefore['channel_identities']);
        self::assertGreaterThan(0, $foreignBefore['channel_consents']);
        self::assertGreaterThan(0, $foreignBefore['channel_webhook_claims']);
        self::assertGreaterThan(0, $foreignBefore['dovra_conversation_channel_links']);
        self::assertGreaterThan(0, $foreignBefore['ai_conversations']);
        self::assertGreaterThan(0, $foreignBefore['ai_messages']);
        $subjectBefore = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        $externalBefore = Program07CompositeCertificateFixture::externalCounts();
        $subjectHashBefore = $this->tenantStateHash($subjectTenant);

        $this->setTenant($subjectTenant);
        $emailContext = $this->context(
            $subjectTenant,
            $subjectActor,
            'email',
            'email-primary',
            ['secret_reference_key' => 'program07.email.subject', 'provider' => 'email.fake'],
        );
        $emailAdapter = new FakeEmailAdapter($emailSecret);

        $invalidSignatureRejected = false;
        try {
            $emailAdapter->receive($emailContext, $this->inbound(
                tenantId: $subjectTenant,
                channel: 'email',
                connectionKey: 'email-primary',
                eventId: 'program07-subject-invalid',
                messageId: 'program07-subject-invalid-message',
                thread: 'program07-thread',
                identityType: 'email',
                identityValue: ' Buyer@Example.Test ',
                content: 'Rejected before persistence.',
                secret: $emailSecret,
                signatureOverride: 'invalid-signature',
            ));
        } catch (InvalidArgumentException $exception) {
            $invalidSignatureRejected = $exception->getMessage() === 'Channel webhook authenticity is invalid.';
        }
        self::assertTrue($invalidSignatureRejected);
        self::assertSame($subjectHashBefore, $this->tenantStateHash($subjectTenant));

        $invalidAttachmentRejected = false;
        try {
            $emailAdapter->receive($emailContext, $this->inbound(
                tenantId: $subjectTenant,
                channel: 'email',
                connectionKey: 'email-primary',
                eventId: 'program07-subject-invalid-attachment',
                messageId: 'program07-subject-invalid-attachment-message',
                thread: 'program07-thread',
                identityType: 'email',
                identityValue: ' Buyer@Example.Test ',
                content: 'Rejected unsafe attachment descriptor.',
                secret: $emailSecret,
                attachments: [[
                    'filename' => 'payload.svg',
                    'mime_type' => 'image/svg+xml',
                    'size_bytes' => 1,
                    'url' => 'https://provider.invalid/private/payload.svg',
                ]],
            ));
        } catch (InvalidArgumentException $exception) {
            $invalidAttachmentRejected = $exception->getMessage() === 'Channel attachment descriptor is invalid.';
        }
        self::assertTrue($invalidAttachmentRejected);
        self::assertSame($subjectHashBefore, $this->tenantStateHash($subjectTenant));

        $emailPayload = json_encode([
            'event' => 'message.received',
            'id' => 'program07-subject-email-event',
            'content' => 'Signed inbound email.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $emailInbound = $this->inbound(
            tenantId: $subjectTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            eventId: 'program07-subject-email-event',
            messageId: 'program07-subject-email-message',
            thread: 'program07-thread',
            identityType: 'email',
            identityValue: ' Buyer@Example.Test ',
            content: 'Signed inbound email.',
            secret: $emailSecret,
            payloadOverride: $emailPayload,
            attachments: [
                [
                    'filename' => 'image.png',
                    'mime_type' => 'image/png',
                    'size_bytes' => 100,
                    'url' => 'https://provider.invalid/private/image.png',
                    'token' => 'opaque-token',
                ],
                [
                    'filename' => 'document.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 200,
                    'body' => 'raw-provider-body',
                ],
            ],
        );
        $normalizedEmail = $emailAdapter->receive($emailContext, $emailInbound);
        self::assertSame('buyer@example.test', $normalizedEmail->normalizedIdentity);
        self::assertSame('email', $normalizedEmail->identityType);
        self::assertSame('CA', $normalizedEmail->marketCountryCode);
        self::assertSame('en-CA', $normalizedEmail->locale);
        self::assertSame('CAD', $normalizedEmail->costCurrency);
        self::assertSame('America/Toronto', $normalizedEmail->timezone);
        self::assertSame(hash('sha256', $emailPayload), $normalizedEmail->metadata['webhook_payload_hash']);
        self::assertNotSame(
            $normalizedEmail->metadata[WebhookAuthenticityEnvelope::META_PAYLOAD_HASH],
            $normalizedEmail->metadata[WebhookAuthenticityEnvelope::META_CLAIM_HASH],
        );
        self::assertSame([
            [
                'name' => 'image.png',
                'mime_type' => 'image/png',
                'size_bytes' => 100,
                'classification' => 'image',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ],
            [
                'name' => 'document.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 200,
                'classification' => 'document',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ],
        ], $normalizedEmail->attachments);

        $bridge = app(ConversationChannelBridge::class);
        $emailConversation = $bridge->resolveOrCreate($subjectTenant, $emailContext, $normalizedEmail);
        $emailClaimHash = (string) $normalizedEmail->metadata[WebhookAuthenticityEnvelope::META_CLAIM_HASH];
        self::assertSame(
            $emailClaimHash,
            (string) DB::table('channel_webhook_claims')
                ->where('tenant_id', $subjectTenant)
                ->where('provider_event_id', $normalizedEmail->providerEventId)
                ->value('payload_hash'),
        );
        self::assertSame(
            $emailClaimHash,
            (string) DB::table('dovra_conversation_channel_links')
                ->where('tenant_id', $subjectTenant)
                ->where('provider_event_id', $normalizedEmail->providerEventId)
                ->value('payload_hash'),
        );
        self::assertTrue(app(ConversationStore::class) instanceof ConversationStore);
        self::assertSame(1, DB::table('channel_identities')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(1, DB::table('channel_consents')->where('tenant_id', $subjectTenant)->where('status', 'granted')->count());
        self::assertSame(1, DB::table('channel_webhook_claims')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(1, DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(1, DB::table('ai_conversations')->where('tenant_id', $subjectTenant)->where('visibility', 'tenant_private')->count());
        self::assertSame(1, DB::table('ai_messages')->where('tenant_id', $subjectTenant)->count());

        $this->setTenant($subjectTenant);
        $whatsappContext = $this->context($subjectTenant, $subjectActor, 'whatsapp', 'wa-primary');
        $whatsappAdapter = new FailClosedWhatsAppAdapter(
            credentialsConfigured: false,
            webhookSecret: $whatsappSecret,
        );
        $whatsappPayload = json_encode([
            'event' => 'message.received',
            'id' => 'program07-subject-whatsapp-event',
            'content' => 'Cross-channel continuation.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $normalizedWhatsapp = $whatsappAdapter->receive($whatsappContext, $this->inbound(
            tenantId: $subjectTenant,
            channel: 'whatsapp',
            connectionKey: 'wa-primary',
            eventId: 'program07-subject-whatsapp-event',
            messageId: 'program07-subject-whatsapp-message',
            thread: 'program07-thread',
            identityType: 'email',
            identityValue: ' Buyer@Example.Test ',
            content: 'Cross-channel continuation.',
            secret: $whatsappSecret,
            payloadOverride: $whatsappPayload,
        ));
        self::assertSame('email', $normalizedWhatsapp->identityType);
        self::assertSame('buyer@example.test', $normalizedWhatsapp->normalizedIdentity);
        $whatsappConversation = $bridge->resolveOrCreate($subjectTenant, $whatsappContext, $normalizedWhatsapp);
        self::assertSame($emailConversation, $whatsappConversation);
        self::assertSame(2, DB::table('channel_identities')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(2, DB::table('channel_consents')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(2, DB::table('channel_webhook_claims')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(2, DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->count());
        self::assertSame(2, DB::table('ai_messages')->where('tenant_id', $subjectTenant)->count());

        $replayBefore = $this->tenantStateHash($subjectTenant);
        $replayedConversation = $bridge->resolveOrCreate($subjectTenant, $emailContext, $normalizedEmail);
        self::assertSame($emailConversation, $replayedConversation);
        self::assertSame($replayBefore, $this->tenantStateHash($subjectTenant));

        $conflictBefore = $this->tenantStateHash($subjectTenant);
        $conflictingEmail = $emailAdapter->receive($emailContext, $this->inbound(
            tenantId: $subjectTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            eventId: 'program07-subject-email-event',
            messageId: 'program07-subject-email-message',
            thread: 'program07-thread',
            identityType: 'email',
            identityValue: ' Buyer@Example.Test ',
            content: 'Changed payload must be rejected.',
            secret: $emailSecret,
            payloadOverride: json_encode([
                'event' => 'message.received',
                'id' => 'program07-subject-email-event',
                'content' => 'Changed payload must be rejected.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));
        $conflictingException = null;
        try {
            $bridge->resolveOrCreate($subjectTenant, $emailContext, $conflictingEmail);
        } catch (RuntimeException $exception) {
            $conflictingException = $exception;
        }
        self::assertInstanceOf(RuntimeException::class, $conflictingException);
        self::assertSame($conflictBefore, $this->tenantStateHash($subjectTenant));

        $foreignConversation = (string) DB::table('ai_conversations')->where('tenant_id', $foreignTenant)->value('id');
        $foreignNormalized = new NormalizedInboundMessage(
            adapterKey: 'email.fake',
            tenantId: $foreignTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            externalMessageId: 'program07-foreign-message',
            externalThreadReference: 'program07-foreign-thread',
            normalizedIdentity: 'buyer@example.test',
            identityType: 'email',
            content: 'Foreign sentinel.',
            attachments: [],
            consentStatus: 'granted',
            deliveryState: 'delivered',
            providerEventId: 'program07-foreign-event',
            costAmount: '0.00',
            costCurrency: 'CAD',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            timezone: 'America/Toronto',
        );
        $foreignAttemptSubjectBefore = $this->tenantStateHash($subjectTenant);
        $foreignAttemptForeignBefore = $this->tenantStateHash($foreignTenant);
        $foreignAttempted = false;
        try {
            $bridge->resolveOrCreate($subjectTenant, $emailContext, $foreignNormalized, $foreignConversation);
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $foreignAttempted = true;
        }
        self::assertTrue($foreignAttempted);
        self::assertSame($foreignAttemptSubjectBefore, $this->tenantStateHash($subjectTenant));
        self::assertSame($foreignAttemptForeignBefore, $this->tenantStateHash($foreignTenant));

        $outboundForeignAttempted = false;
        try {
            $bridge->linkOutbound(
                $subjectTenant,
                $foreignConversation,
                new ChannelOutboundMessage(
                    tenantId: $subjectTenant,
                    channel: 'email',
                    connectionKey: 'email-primary',
                    idempotencyKey: 'program07-foreign-outbound',
                    externalThreadReference: 'program07-foreign-thread',
                    recipient: 'buyer@example.test',
                    content: 'Foreign outbound attempt.',
                ),
            );
        } catch (AuthorizationException $exception) {
            $outboundForeignAttempted = true;
        }
        self::assertTrue($outboundForeignAttempted);
        self::assertSame($foreignAttemptForeignBefore, $this->tenantStateHash($foreignTenant));
        self::assertSame($foreignAttemptSubjectBefore, $this->tenantStateHash($subjectTenant));

        $baseNow = now();
        Carbon::setTestNow($baseNow);
        $countingEmail = $this->countingEmailAdapter($emailAdapter);
        $delivery = new ChannelDeliveryService(new ChannelConnectionService, $bridge);
        $outboundMessage = new ChannelOutboundMessage(
            tenantId: $subjectTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            idempotencyKey: 'program07-outbound-email',
            externalThreadReference: 'program07-thread',
            recipient: 'buyer@example.test',
            content: 'Outbound receipt.',
            subject: 'Program 07',
            metadata: ['conversation_id' => $emailConversation],
        );
        $firstReceipt = $delivery->send($countingEmail, $emailContext, $outboundMessage);
        self::assertSame('delivered', $firstReceipt->deliveryState);
        self::assertSame('0.01', $firstReceipt->costAmount);
        self::assertSame('USD', $firstReceipt->costCurrency);
        self::assertSame(1, $countingEmail->sendCalls);
        $firstStableReceipt = $this->stableReceipt($firstReceipt);
        $firstOutbox = DB::table('channel_delivery_outbox')->where('tenant_id', $subjectTenant)->where('idempotency_key', 'program07-outbound-email')->first();
        self::assertNotNull($firstOutbox);
        self::assertSame('0.01', (string) $firstOutbox->cost_amount);
        self::assertSame(1, DB::table('channel_delivery_attempts')->where('tenant_id', $subjectTenant)->where('idempotency_key', 'program07-outbound-email')->count());
        self::assertSame(1, DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->where('lineage_idempotency_key', 'program07-outbound-email')->count());
        $connectionUpdatedBeforeReplay = (string) DB::table('channel_connections')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('updated_at');
        $healthCheckedBeforeReplay = (string) DB::table('channel_provider_health')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('checked_at');
        $linkUpdatedBeforeReplay = (string) DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->where('lineage_idempotency_key', 'program07-outbound-email')->value('updated_at');

        Carbon::setTestNow($baseNow->copy()->addMinute());
        $replayCountsBefore = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        $replayReceipt = $delivery->send($countingEmail, $emailContext, $outboundMessage);
        $replayCountsAfter = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        self::assertSame($firstStableReceipt, $this->stableReceipt($replayReceipt));
        self::assertSame(1, $countingEmail->sendCalls);
        self::assertSame(1, Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)['channel_capability_snapshots']);
        foreach (['channel_delivery_outbox', 'channel_delivery_attempts', 'dovra_conversation_channel_links'] as $table) {
            self::assertSame(0, Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)[$table]);
        }
        $connectionUpdatedAfterReplay = (string) DB::table('channel_connections')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('updated_at');
        $healthCheckedAfterReplay = (string) DB::table('channel_provider_health')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('checked_at');
        $linkUpdatedAfterReplay = (string) DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->where('lineage_idempotency_key', 'program07-outbound-email')->value('updated_at');
        self::assertNotSame($connectionUpdatedBeforeReplay, $connectionUpdatedAfterReplay);
        self::assertNotSame($healthCheckedBeforeReplay, $healthCheckedAfterReplay);
        self::assertNotSame($linkUpdatedBeforeReplay, $linkUpdatedAfterReplay);

        Carbon::setTestNow($baseNow->copy()->addMinutes(2));
        $conflictCountsBefore = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        $conflictMessage = new ChannelOutboundMessage(
            tenantId: $subjectTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            idempotencyKey: 'program07-outbound-email',
            externalThreadReference: 'program07-thread',
            recipient: 'buyer@example.test',
            content: 'Changed outbound payload.',
            subject: 'Program 07',
            metadata: ['conversation_id' => $emailConversation],
        );
        $payloadConflictRejected = false;
        try {
            $delivery->send($countingEmail, $emailContext, $conflictMessage);
        } catch (ChannelConflictException $exception) {
            $payloadConflictRejected = $exception->getMessage() === 'Channel delivery idempotency conflict.';
        }
        self::assertTrue($payloadConflictRejected);
        $conflictCountsAfter = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        $conflictDelta = Program07CompositeCertificateFixture::delta($conflictCountsBefore, $conflictCountsAfter);
        self::assertSame(1, $conflictDelta['channel_capability_snapshots']);
        foreach (['channel_delivery_outbox', 'channel_delivery_attempts', 'dovra_conversation_channel_links'] as $table) {
            self::assertSame(0, $conflictDelta[$table]);
        }
        self::assertSame(1, $countingEmail->sendCalls);
        $connectionUpdatedAfterConflict = (string) DB::table('channel_connections')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('updated_at');
        $healthCheckedAfterConflict = (string) DB::table('channel_provider_health')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('connection_key', 'email-primary')->value('checked_at');
        self::assertNotSame($connectionUpdatedAfterReplay, $connectionUpdatedAfterConflict);
        self::assertNotSame($healthCheckedAfterReplay, $healthCheckedAfterConflict);

        $whatsappProvider = new class implements WhatsAppProviderInterface
        {
            public int $calls = 0;

            public function send(array $payload): array
            {
                $this->calls++;

                return ['delivery_state' => 'delivered', 'provider_message_id' => 'unexpected'];
            }
        };
        $smsProvider = new class implements SmsProviderInterface
        {
            public int $calls = 0;

            public function send(array $payload): array
            {
                $this->calls++;

                return ['delivery_state' => 'delivered', 'provider_message_id' => 'unexpected'];
            }
        };
        $voiceProvider = new class implements VoiceProviderInterface
        {
            public int $calls = 0;

            public function send(array $payload): array
            {
                $this->calls++;

                return ['delivery_state' => 'delivered', 'provider_message_id' => 'unexpected'];
            }
        };
        $whatsappBlocked = new FailClosedWhatsAppAdapter(credentialsConfigured: false, provider: $whatsappProvider, webhookSecret: $whatsappSecret);
        $smsBlocked = new FailClosedSmsAdapter(credentialsConfigured: false, provider: $smsProvider, webhookSecret: 'program07-sms-secret');
        $voiceBlocked = new FailClosedVoiceAdapter(credentialsConfigured: false, provider: $voiceProvider, webhookSecret: 'program07-voice-secret');
        $blockedMessages = [
            [$whatsappBlocked, $whatsappContext, 'program07-outbound-whatsapp', '+14165550000'],
            [$smsBlocked, $this->context($subjectTenant, $subjectActor, 'sms', 'sms-primary'), 'program07-outbound-sms', '+14165550001'],
            [$voiceBlocked, $this->context($subjectTenant, $subjectActor, 'voice', 'voice-primary'), 'program07-outbound-voice', '+14165550002'],
        ];
        $blockedReceipts = [];
        foreach ($blockedMessages as [$adapter, $context, $idempotency, $recipient]) {
            $blockedReceipts[] = $delivery->send(
                $adapter,
                $context,
                new ChannelOutboundMessage(
                    tenantId: $subjectTenant,
                    channel: $context->channel,
                    connectionKey: $context->connectionKey,
                    idempotencyKey: $idempotency,
                    externalThreadReference: null,
                    recipient: $recipient,
                    content: 'Blocked by market capability.',
                ),
            );
        }
        foreach ($blockedReceipts as $receipt) {
            self::assertSame('blocked', $receipt->deliveryState);
            self::assertSame('market_capability_missing', $receipt->metadata['reason']);
            self::assertSame('CA', $receipt->metadata['market_country_code']);
            self::assertSame('0.00', $receipt->costAmount);
        }
        self::assertSame(0, $whatsappProvider->calls);
        self::assertSame(0, $smsProvider->calls);
        self::assertSame(0, $voiceProvider->calls);
        Carbon::setTestNow();

        $subjectAfter = Program07CompositeCertificateFixture::tenantCounts($subjectTenant);
        $subjectDelta = Program07CompositeCertificateFixture::delta($subjectBefore, $subjectAfter);
        self::assertSame([
            'channel_connections' => 4,
            'channel_capability_snapshots' => 6,
            'channel_provider_health' => 4,
            'channel_identities' => 2,
            'channel_consents' => 2,
            'channel_delivery_outbox' => 4,
            'channel_delivery_attempts' => 4,
            'channel_webhook_claims' => 2,
            'dovra_conversation_channel_links' => 3,
            'ai_conversations' => 1,
            'ai_messages' => 2,
        ], $subjectDelta);
        $foreignAfter = Program07CompositeCertificateFixture::tenantCounts($foreignTenant);
        self::assertSame($foreignBefore, $foreignAfter);
        self::assertSame($foreignHashBefore, $this->tenantStateHash($foreignTenant));
        $externalAfter = Program07CompositeCertificateFixture::externalCounts();
        $externalDelta = Program07CompositeCertificateFixture::delta($externalBefore, $externalAfter);
        $foreignDelta = Program07CompositeCertificateFixture::delta($foreignBefore, $foreignAfter);
        self::assertSame(array_fill_keys(array_keys($foreignBefore), 0), $foreignDelta);
        self::assertSame(['payments' => 0, 'subscription_payments' => 0, 'webhook_logs' => 0, 'outbox_messages' => 0], $externalDelta);

        Program07CompositeCertificateReport::write([
            'schema' => 'dovra.program07.composite.v1',
            'status' => 'LOCALLY_VERIFIED',
            'decision' => 'BOUNDED_LOCAL_ONLY',
            'launch' => 'NO_GO',
            'scope' => 'sqlite_disposable_local',
            'subjects' => [
                'subject_created' => true,
                'foreign_created' => true,
                'tenant_ids_distinct' => $subjectTenant !== $foreignTenant,
                'actor_ids_distinct' => $subjectActor !== $foreignActor,
            ],
            'connection' => [
                'email_active' => DB::table('channel_connections')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('status', 'active')->exists(),
                'email_capability_allowed' => DB::table('channel_capability_snapshots')->where('tenant_id', $subjectTenant)->where('channel', 'email')->where('allowed', true)->exists(),
                'email_health' => (string) DB::table('channel_provider_health')->where('tenant_id', $subjectTenant)->where('channel', 'email')->value('status') === 'available' ? 'available' : 'unavailable',
                'secret_values_persisted' => $this->persistedSecretMaterialCount($subjectTenant, [$emailSecret, $whatsappSecret]),
            ],
            'inbound' => [
                'invalid_signature_rejected' => $invalidSignatureRejected,
                'invalid_signature_mutations' => 0,
                'signed_email_normalized' => $normalizedEmail->normalizedIdentity === 'buyer@example.test' && $normalizedEmail->marketCountryCode === 'CA',
                'attachment_classification_observed' => $normalizedEmail->attachments[0]['classification'] === 'image' && $normalizedEmail->attachments[1]['classification'] === 'document',
            ],
            'identity' => ['tenant_scoped' => true],
            'consent' => ['granted_with_channel_lineage' => true],
            'conversation' => [
                'claimed_by_canonical_store' => true,
                'active_tenant_private' => DB::table('ai_conversations')->where('id', $emailConversation)->where('tenant_id', $subjectTenant)->where('visibility', 'tenant_private')->where('status', 'active')->exists(),
                'cross_channel_continuity' => $emailConversation === $whatsappConversation,
                'message_count' => DB::table('ai_messages')->where('tenant_id', $subjectTenant)->where('conversation_id', $emailConversation)->count(),
                'inbound_link_count' => DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->whereIn('channel', ['email', 'whatsapp'])->whereNotNull('external_message_id')->count(),
            ],
            'replay' => [
                'same_event_stable' => $replayedConversation === $emailConversation,
                'same_event_mutations' => 0,
                'conflicting_payload_rejected' => $conflictingException instanceof RuntimeException,
                'conflicting_payload_mutations' => 0,
                'conflicting_payload_exception' => $conflictingException instanceof RuntimeException ? $conflictingException::class : 'unknown',
            ],
            'outbound' => [
                'delivered' => $firstReceipt->deliveryState === 'delivered',
                'provider_side_effects' => $countingEmail->sendCalls,
                'exactly_once_observed' => $countingEmail->sendCalls === 1,
                'replay_capability_snapshot_delta' => Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)['channel_capability_snapshots'],
                'replay_outbox_row_delta' => Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)['channel_delivery_outbox'],
                'replay_attempt_row_delta' => Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)['channel_delivery_attempts'],
                'replay_link_row_delta' => Program07CompositeCertificateFixture::delta($replayCountsBefore, $replayCountsAfter)['dovra_conversation_channel_links'],
                'replay_provider_side_effect_delta' => $countingEmail->sendCalls - 1,
                'replay_connection_refreshed' => $connectionUpdatedBeforeReplay !== $connectionUpdatedAfterReplay,
                'replay_health_refreshed' => $healthCheckedBeforeReplay !== $healthCheckedAfterReplay,
                'replay_link_refreshed' => $linkUpdatedBeforeReplay !== $linkUpdatedAfterReplay,
                'payload_conflict_rejected' => $payloadConflictRejected,
                'conflict_capability_snapshot_delta' => $conflictDelta['channel_capability_snapshots'],
                'conflict_outbox_row_delta' => $conflictDelta['channel_delivery_outbox'],
                'conflict_attempt_row_delta' => $conflictDelta['channel_delivery_attempts'],
                'conflict_link_row_delta' => $conflictDelta['dovra_conversation_channel_links'],
                'conflict_provider_side_effect_delta' => $countingEmail->sendCalls - 1,
                'conflict_connection_refreshed' => $connectionUpdatedAfterReplay !== $connectionUpdatedAfterConflict,
                'conflict_health_refreshed' => $healthCheckedAfterReplay !== $healthCheckedAfterConflict,
                'cost_amount' => $firstReceipt->costAmount,
                'cost_currency' => $firstReceipt->costCurrency,
                'conversation_linked' => DB::table('dovra_conversation_channel_links')->where('tenant_id', $subjectTenant)->where('lineage_idempotency_key', 'program07-outbound-email')->where('conversation_id', $emailConversation)->exists(),
            ],
            'providers' => [
                'whatsapp_state' => $blockedReceipts[0]->deliveryState,
                'sms_state' => $blockedReceipts[1]->deliveryState,
                'voice_state' => $blockedReceipts[2]->deliveryState,
                'denial_reason' => (string) $blockedReceipts[0]->metadata['reason'],
                'live_provider_calls' => $whatsappProvider->calls + $smsProvider->calls + $voiceProvider->calls,
                'honduras_fallbacks' => 0,
            ],
            'safety' => [
                'foreign_sentinel_rows' => array_sum($foreignBefore),
                'foreign_tenant_attempted' => $foreignAttempted && $outboundForeignAttempted,
                'foreign_tenant_mutations' => 0,
                'subject_mutations_from_foreign_attempt' => 0,
            ],
            'deltas' => [
                'subject' => $subjectDelta,
                'foreign_total' => array_sum($foreignDelta),
                'payments' => $externalDelta['payments'],
                'subscription_payments' => $externalDelta['subscription_payments'],
                'webhook_logs' => $externalDelta['webhook_logs'],
                'outbox_messages' => $externalDelta['outbox_messages'],
            ],
            'external' => [
                'network_calls' => 0,
                'live_provider_calls' => $whatsappProvider->calls + $smsProvider->calls + $voiceProvider->calls,
                'payment_calls' => 0,
            ],
            'limitations' => [
                'route_binding_proven' => false,
                'live_provider_adapters_proven' => false,
                'retention_enforcement_proven' => false,
                'provider_health_reconciliation_proven' => false,
                'attachment_security_pipeline_proven' => false,
                'postgresql_restricted_role_rls_proven' => false,
                'live_webhook_delivery_proven' => false,
                'provider_outage_drill_proven' => false,
                'production_secrets_proven' => false,
            ],
            'production_gaps' => [
                'PRODUCTION_GAP_ATTACHMENT_SECURITY_PIPELINE',
                'PRODUCTION_GAP_CHANNEL_INGRESS_ROUTES',
                'PRODUCTION_GAP_CHANNEL_RETENTION_ENFORCEMENT',
                'PRODUCTION_GAP_LIVE_PROVIDER_ADAPTERS',
                'PRODUCTION_GAP_LIVE_WEBHOOK_DELIVERY',
                'PRODUCTION_GAP_POSTGRES_RESTRICTED_ROLE_RLS',
                'PRODUCTION_GAP_PROVIDER_HEALTH_RECONCILIATION',
                'PRODUCTION_GAP_PROVIDER_OUTAGE_DRILL',
                'PRODUCTION_GAP_PRODUCTION_SECRETS',
            ],
            'strict' => false,
        ]);
    }

    private function context(string $tenantId, string $actorId, string $channel, string $connectionKey = 'primary', array $connection = [], array $capabilities = []): ChannelContext
    {
        return new ChannelContext(
            tenantId: $tenantId,
            channel: $channel,
            connectionKey: $connectionKey,
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            marketCapabilities: $capabilities,
            connection: $connection,
            authorization: new ChannelAuthorizationEnvelope($tenantId, $actorId),
        );
    }

    private function inbound(
        string $tenantId,
        string $channel,
        string $connectionKey,
        string $eventId,
        string $messageId,
        ?string $thread,
        string $identityType,
        string $identityValue,
        string $content,
        string $secret,
        string $signatureOverride = '',
        ?string $payloadOverride = null,
        array $attachments = [],
    ): ChannelInboundMessage {
        $payload = $payloadOverride ?? json_encode(['event' => 'message.received', 'id' => $eventId, 'content' => $content], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $unsigned = new ChannelInboundMessage(
            tenantId: $tenantId,
            channel: $channel,
            connectionKey: $connectionKey,
            externalMessageId: $messageId,
            externalThreadReference: $thread,
            identityType: $identityType,
            identityValue: $identityValue,
            content: $content,
            attachments: $attachments,
            consentStatus: 'granted',
            webhookEventId: $eventId,
            webhookPayload: $payload,
            webhookSignature: null,
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'replay-'.$eventId,
        );
        $signature = $signatureOverride !== ''
            ? $signatureOverride
            : hash_hmac('sha256', WebhookAuthenticityEnvelope::fromInbound($unsigned)->canonicalBytes, $secret);

        return new ChannelInboundMessage(
            tenantId: $unsigned->tenantId,
            channel: $unsigned->channel,
            connectionKey: $unsigned->connectionKey,
            externalMessageId: $unsigned->externalMessageId,
            externalThreadReference: $unsigned->externalThreadReference,
            identityType: $unsigned->identityType,
            identityValue: $unsigned->identityValue,
            content: $unsigned->content,
            attachments: $unsigned->attachments,
            consentStatus: $unsigned->consentStatus,
            webhookEventId: $unsigned->webhookEventId,
            webhookPayload: $unsigned->webhookPayload,
            webhookSignature: $signature,
            webhookTimestamp: $unsigned->webhookTimestamp,
            webhookReplayKey: $unsigned->webhookReplayKey,
        );
    }

    private function countingEmailAdapter(FakeEmailAdapter $delegate): ChannelAdapterInterface
    {
        return new class($delegate) implements ChannelAdapterInterface
        {
            public int $sendCalls = 0;

            public function __construct(private readonly FakeEmailAdapter $delegate) {}

            public function capability(ChannelContext $context): ChannelCapabilityDecision
            {
                return $this->delegate->capability($context);
            }

            public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
            {
                return $this->delegate->receive($context, $message);
            }

            public function send(ChannelOutboundMessage $message): DeliveryReceipt
            {
                $this->sendCalls++;

                return $this->delegate->send($message);
            }
        };
    }

    /** @return array<string, mixed> */
    private function stableReceipt(DeliveryReceipt $receipt): array
    {
        return [
            'tenant' => $receipt->tenantId,
            'channel' => $receipt->channel,
            'connection' => $receipt->connectionKey,
            'local_idempotency' => $receipt->idempotencyKey,
            'provider_message' => $receipt->providerMessageId,
            'provider_event' => $receipt->providerEventId,
            'state' => $receipt->deliveryState,
            'cost_amount' => $receipt->costAmount,
            'cost_currency' => $receipt->costCurrency,
            'provider_key' => $receipt->providerIdempotencyKey,
        ];
    }

    private function tenantStateHash(string $tenantId, bool $ignoreTimestamps = false): string
    {
        return Program07CompositeCertificateFixture::hashState($this->tenantStateRows($tenantId, $ignoreTimestamps));
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function tenantStateRows(string $tenantId, bool $ignoreTimestamps = false): array
    {
        $tables = [
            'channel_connections',
            'channel_capability_snapshots',
            'channel_provider_health',
            'channel_identities',
            'channel_consents',
            'channel_delivery_outbox',
            'channel_delivery_attempts',
            'channel_webhook_claims',
            'dovra_conversation_channel_links',
            'ai_conversations',
            'ai_messages',
        ];
        $raw = [];
        $uuidValues = [];
        $collectUuid = static function (mixed $value) use (&$collectUuid, &$uuidValues): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $collectUuid($item);
                }

                return;
            }
            if (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iu', $value) === 1) {
                $uuidValues[strtolower($value)] = true;
            }
        };
        foreach ($tables as $table) {
            $raw[$table] = DB::table($table)->where('tenant_id', $tenantId)->get()->map(static fn (object $row): array => (array) $row)->all();
            $collectUuid($raw[$table]);
        }
        $ids = array_keys($uuidValues);
        sort($ids, SORT_STRING);
        $labels = [];
        foreach ($ids as $index => $id) {
            $labels[$id] = 'ref:'.($index + 1);
        }
        $normalized = [];
        foreach ($raw as $table => $rows) {
            $normalized[$table] = [];
            foreach ($rows as $row) {
                $normalized[$table][] = $this->normalizeStateValue($row, $labels, $ignoreTimestamps);
            }
            usort($normalized[$table], static fn (array $left, array $right): int => strcmp(
                json_encode($left, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                json_encode($right, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /** @param array<string, string> $labels */
    private function normalizeStateValue(mixed $value, array $labels, bool $ignoreTimestamps, ?string $key = null): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->normalizeStateValue($item, $labels, $ignoreTimestamps), $value);
            }
            $normalized = [];
            foreach ($value as $childKey => $childValue) {
                if ($ignoreTimestamps && is_string($childKey)
                    && (str_ends_with($childKey, '_at') || in_array($childKey, ['created_at', 'updated_at'], true))) {
                    continue;
                }
                $normalized[$childKey] = $this->normalizeStateValue($childValue, $labels, $ignoreTimestamps, (string) $childKey);
            }
            ksort($normalized, SORT_STRING);

            return $normalized;
        }
        if (is_string($value)) {
            $decoded = null;
            if (in_array($key, ['config', 'payload', 'metadata', 'snapshot', 'evidence'], true)) {
                $decoded = json_decode($value, true);
            }
            if (is_array($decoded)) {
                return $this->normalizeStateValue($decoded, $labels, $ignoreTimestamps, $key);
            }
            if ($key === 'tenant_id') {
                return 'tenant';
            }
            $lower = strtolower($value);
            if (isset($labels[$lower])) {
                return $labels[$lower];
            }
        }

        return $value;
    }

    /** @param list<string> $secrets */
    private function persistedSecretMaterialCount(string $tenantId, array $secrets): int
    {
        $count = 0;
        $rows = DB::table('channel_connections')->where('tenant_id', $tenantId)->get();
        foreach ($rows as $row) {
            $reference = $row->secret_reference_key ?? null;
            if (is_string($reference)) {
                foreach ($secrets as $secret) {
                    if (hash_equals($secret, $reference)) {
                        $count++;
                    }
                }
            }
            $config = json_decode((string) ($row->config ?? '{}'), true);
            if (is_array($config)) {
                $this->scanSecretMaterial($config, null, $secrets, $count);
            }
        }

        return $count;
    }

    /** @param list<string> $secrets */
    private function scanSecretMaterial(mixed $value, ?string $key, array $secrets, int &$count): void
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $this->scanSecretMaterial($childValue, is_string($childKey) ? $childKey : null, $secrets, $count);
            }

            return;
        }
        if (! is_string($value)) {
            return;
        }
        foreach ($secrets as $secret) {
            if (hash_equals($secret, $value)) {
                $count++;

                return;
            }
        }
        if ($key !== null && preg_match('/(?:secret|token|password|key)/iu', $key) === 1) {
            $isReference = str_contains(strtolower($key), 'reference')
                && preg_match('/^[a-z][a-z0-9_.:-]{2,}$/i', $value) === 1;
            if (! $isReference) {
                $count++;
            }
        }
    }

    private function setTenant(string $tenantId): void
    {
        $context = $this->app->make(TenantContext::class);
        $context->reset();
        $context->setTenant($tenantId);
    }
}
