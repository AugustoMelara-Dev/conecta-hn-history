<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAttachmentPolicy;
use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\WebChat\FakeWebChatAdapter;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class ChannelAttachmentSecurityTest extends TestCase
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

    public function test_policy_canonicalizes_an_allowed_descriptor_without_retaining_provider_data(): void
    {
        $canonical = ChannelAttachmentPolicy::canonicalize([
            [
                'filename' => ' Receipt.PDF ',
                'mimeType' => ' APPLICATION/PDF ',
                'size' => 2048,
                'provider_attachment_id' => 'opaque-provider-id',
                'url' => 'https://provider.invalid/private/object',
                'token' => 'secret-token',
            ],
        ]);

        self::assertSame([
            [
                'name' => 'Receipt.PDF',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048,
                'classification' => 'document',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
                'provider_reference_sha256' => hash('sha256', 'opaque-provider-id'),
            ],
        ], $canonical);
    }

    public function test_policy_canonicalizes_every_allowlisted_mime_and_legacy_alias(): void
    {
        $descriptors = [
            ['name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1],
            ['filename' => 'photo.jpeg', 'mimeType' => ' IMAGE/JPEG ', 'size' => 2],
            ['name' => 'diagram.png', 'mime_type' => 'image/png', 'size_bytes' => 3],
            ['name' => 'screen.webp', 'mime_type' => 'image/webp', 'size_bytes' => 4],
            ['name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5],
            ['name' => 'notes.txt', 'mime_type' => 'text/plain', 'size_bytes' => 6],
            ['name' => 'song.mp3', 'mime_type' => 'audio/mpeg', 'size_bytes' => 7],
            ['name' => 'recording.ogg', 'mime_type' => 'audio/ogg', 'size_bytes' => 8],
            ['name' => 'recording.oga', 'mime_type' => 'audio/ogg', 'size_bytes' => 9],
            ['name' => 'voice.wav', 'mime_type' => 'audio/wav', 'size_bytes' => 10],
        ];

        $canonical = ChannelAttachmentPolicy::canonicalize($descriptors);

        self::assertSame(
            ['image', 'image', 'image', 'image', 'document', 'document', 'audio', 'audio', 'audio', 'audio'],
            array_column($canonical, 'classification'),
        );
        self::assertSame(
            array_map(static fn (array $descriptor): string => (string) ($descriptor['name'] ?? $descriptor['filename']), $descriptors),
            array_column($canonical, 'name'),
        );
        self::assertSame(array_map(static fn (array $descriptor): int => (int) ($descriptor['size_bytes'] ?? $descriptor['size']), $descriptors), array_column($canonical, 'size_bytes'));
        foreach ($canonical as $descriptor) {
            self::assertSame('descriptor_only', $descriptor['security_status']);
            self::assertFalse($descriptor['content_verified']);
            self::assertFalse($descriptor['download_allowed']);
        }
    }

    public function test_policy_enforces_descriptor_count_and_size_boundaries(): void
    {
        $ten = [];
        for ($index = 1; $index <= 10; $index++) {
            $ten[] = ['name' => 'file-'.$index.'.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1];
        }
        self::assertCount(10, ChannelAttachmentPolicy::canonicalize($ten));

        $this->assertInvalid(array_merge($ten, [['name' => 'file-11.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1]]));
        self::assertSame(10_485_760, ChannelAttachmentPolicy::canonicalize([
            ['name' => 'maximum.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_760],
        ])[0]['size_bytes']);
        $this->assertInvalid([['name' => 'zero.txt', 'mime_type' => 'text/plain', 'size_bytes' => 0]]);
        $this->assertInvalid([['name' => 'large.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_761]]);
        $this->assertInvalid([
            ['name' => 'aggregate-a.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_760],
            ['name' => 'aggregate-b.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_760],
            ['name' => 'aggregate-c.txt', 'mime_type' => 'text/plain', 'size_bytes' => 6_214_401],
        ]);
        self::assertCount(3, ChannelAttachmentPolicy::canonicalize([
            ['name' => 'aggregate-a.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_760],
            ['name' => 'aggregate-b.txt', 'mime_type' => 'text/plain', 'size_bytes' => 10_485_760],
            ['name' => 'aggregate-c.txt', 'mime_type' => 'text/plain', 'size_bytes' => 5_242_880],
        ]));
    }

    public function test_policy_rejects_missing_conflicting_and_wrong_scalar_aliases(): void
    {
        $invalid = [
            [['name' => 'file.txt', 'mime_type' => 'text/plain']],
            [['filename' => 'file.txt', 'name' => 'other.txt', 'mime_type' => 'text/plain', 'size' => 1]],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'mimeType' => 'text/plain', 'size' => 1]],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => 1, 'size_bytes' => 1]],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => '1']],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => 1.0]],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => true]],
            [['name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => 1, 'provider_attachment_id' => 'a', 'id' => 'b']],
            ['not-an-array-descriptor'],
        ];

        foreach ($invalid as $attachments) {
            $this->assertInvalid($attachments);
        }
    }

    public function test_policy_rejects_unsafe_mime_names_extensions_references_and_duplicates(): void
    {
        foreach ([
            'image/svg+xml',
            'text/html',
            'application/javascript',
            'application/octet-stream',
            "\x00text/plain",
            "text/plain\n",
            "\x0Btext/plain",
            str_repeat(' ', 120).'text/plain',
            "\xC3\x28text/plain",
        ] as $mime) {
            $this->assertInvalid([['name' => 'payload.txt', 'mime_type' => $mime, 'size_bytes' => 1]]);
        }
        foreach ([
            ['name' => 'payload.pdf', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => 'payload', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => '../payload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => 'folder\\payload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => 'folder:payload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => '..', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => "payload\0.txt", 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => "payload.txt\0", 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => "payload.txt\n", 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => str_repeat('a', 256).'.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1],
            ['name' => 'payload.txt', 'mime_type' => str_repeat('a', 128), 'size_bytes' => 1],
            ['name' => 'payload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'id' => "provider\nref"],
            ['name' => 'payload.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'id' => str_repeat('a', 513)],
        ] as $descriptor) {
            $this->assertInvalid([$descriptor]);
        }
        $duplicate = ['name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1];
        $this->assertInvalid([$duplicate, [...$duplicate, 'name' => ' report.pdf ']]);
    }

    public function test_direct_policy_calls_use_no_database_queries_and_never_leak_values(): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $secret = 'super-secret-provider-reference';
        $canonical = ChannelAttachmentPolicy::canonicalize([[
            'name' => 'safe.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 1,
            'provider_reference' => $secret,
            'headers' => ['Authorization' => 'Bearer secret'],
            'body' => 'raw bytes',
            'data' => 'base64-data',
            'base64' => 'base64-data',
            'path' => '/private/path',
            'url' => 'https://example.invalid/private',
            'uri' => 'provider://private',
            'token' => 'token-value',
            'authorization' => 'header-value',
            'contents' => 'content',
        ]]);
        self::assertSame([], DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();
        self::assertArrayNotHasKey('provider_reference', $canonical[0]);
        self::assertArrayNotHasKey('url', $canonical[0]);
        self::assertSame(hash('sha256', $secret), $canonical[0]['provider_reference_sha256']);

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        self::assertSame([
            [
                'name' => 'classifier.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => 1,
                'classification' => 'document',
                'security_status' => 'descriptor_only',
                'content_verified' => false,
                'download_allowed' => false,
            ],
        ], AttachmentClassifier::classify([[
            'name' => 'classifier.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 1,
            'body' => 'must-not-survive',
        ]]));
        self::assertSame([], DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        try {
            ChannelAttachmentPolicy::canonicalize([[
                'name' => '../'.$secret.'.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => 1,
            ]]);
            self::fail('Expected unsafe descriptor to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel attachment descriptor is invalid.', $exception->getMessage());
            self::assertStringNotContainsString($secret, $exception->getMessage());
        }
    }

    public function test_unsigned_web_chat_rejects_invalid_descriptor_before_normalization_with_foreign_state_unchanged(): void
    {
        [$foreignTenant, $foreignActor] = $this->seedForeignSentinel();
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = $this->context($tenant, $actor, 'web_chat', 'web-chat-local');
        $adapter = new FakeWebChatAdapter;
        $before = self::protectedTableStateHash();

        $normalized = $adapter->receive($context, new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'web_chat',
            connectionKey: 'web-chat-local',
            externalMessageId: 'web-attachment-valid',
            externalThreadReference: 'web-thread',
            identityType: 'anonymous_session',
            identityValue: 'session-attachment',
            content: 'valid attachment',
            attachments: [['name' => 'photo.png', 'mime_type' => 'image/png', 'size_bytes' => 1]],
        ));
        self::assertSame('descriptor_only', $normalized->attachments[0]['security_status']);
        self::assertFalse($normalized->attachments[0]['content_verified']);
        self::assertFalse($normalized->attachments[0]['download_allowed']);

        try {
            $adapter->receive($context, new ChannelInboundMessage(
                tenantId: (string) $tenant->id,
                channel: 'web_chat',
                connectionKey: 'web-chat-local',
                externalMessageId: 'web-attachment-invalid',
                externalThreadReference: 'web-thread',
                identityType: 'anonymous_session',
                identityValue: 'session-attachment',
                content: 'invalid attachment',
                attachments: [['name' => 'payload.svg', 'mime_type' => 'image/svg+xml', 'size_bytes' => 1]],
            ));
            self::fail('Expected invalid web-chat descriptor to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel attachment descriptor is invalid.', $exception->getMessage());
        }
        self::assertSame($before, self::protectedTableStateHash());
        self::assertGreaterThan(0, DB::table('channel_webhook_claims')->where('tenant_id', $foreignTenant->id)->count());
        self::assertNotSame('', (string) $foreignActor->id);
    }

    public function test_signed_email_rejects_invalid_descriptor_after_auth_and_authenticates_before_classification(): void
    {
        [$foreignTenant] = $this->seedForeignSentinel();
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $context = $this->context($tenant, $actor, 'email', 'email-local');
        $adapter = new FakeEmailAdapter('email-attachment-secret');
        $valid = new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'email-attachment-valid',
            externalThreadReference: 'email-thread',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'valid attachment',
            attachments: [['name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2]],
            webhookEventId: 'email-attachment-event-valid',
            webhookPayload: '{"attachment":true}',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'email-attachment-replay-valid',
        );
        $normalized = $adapter->receive($context, self::signedMessage($valid, 'email-attachment-secret'));
        self::assertSame('descriptor_only', $normalized->attachments[0]['security_status']);
        $before = self::protectedTableStateHash();

        $invalidDescriptor = new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'email-attachment-invalid',
            externalThreadReference: 'email-thread',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'invalid attachment',
            attachments: [['name' => 'report.pdf', 'mime_type' => 'application/zip', 'size_bytes' => 2]],
            webhookEventId: 'email-attachment-event-invalid',
            webhookPayload: '{"attachment":false}',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'email-attachment-replay-invalid',
        );
        try {
            $adapter->receive($context, self::signedMessage($invalidDescriptor, 'email-attachment-secret'));
            self::fail('Expected valid HMAC plus invalid descriptor to fail closed.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel attachment descriptor is invalid.', $exception->getMessage());
        }
        self::assertSame($before, self::protectedTableStateHash());

        $invalidSignature = new ChannelInboundMessage(
            tenantId: $tenant->id,
            channel: 'email',
            connectionKey: 'email-local',
            externalMessageId: 'email-attachment-invalid-signature',
            externalThreadReference: 'email-thread',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'invalid signature',
            attachments: [['name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2]],
            webhookEventId: 'email-attachment-event-invalid-signature',
            webhookPayload: '{"attachment":true}',
            webhookSignature: 'malformed-signature',
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'email-attachment-replay-invalid-signature',
        );
        try {
            $adapter->receive($context, $invalidSignature);
            self::fail('Expected invalid signature to fail before descriptor validation.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel webhook authenticity is invalid.', $exception->getMessage());
        }
        self::assertSame($before, self::protectedTableStateHash());
        self::assertGreaterThan(0, DB::table('channel_webhook_claims')->where('tenant_id', $foreignTenant->id)->count());
    }

    private function assertInvalid(array $attachments): void
    {
        try {
            ChannelAttachmentPolicy::canonicalize($attachments);
            self::fail('Expected invalid attachment descriptor.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Channel attachment descriptor is invalid.', $exception->getMessage());
        }
    }

    private function context(Tenant $tenant, User $actor, string $channel, string $connectionKey): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: $connectionKey,
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->id, (string) $actor->id),
        );
    }

    /** @return array{0: Tenant, 1: User} */
    private function seedForeignSentinel(): array
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->create();
        GlobalMarketFixture::assignCanada($tenant);
        app(TenantContext::class)->setTenant((string) $tenant->id);
        $context = $this->context($tenant, $actor, 'web_chat', 'foreign-web-chat');
        $message = new ChannelInboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'web_chat',
            connectionKey: 'foreign-web-chat',
            externalMessageId: 'foreign-attachment-sentinel',
            externalThreadReference: 'foreign-thread',
            identityType: 'anonymous_session',
            identityValue: 'foreign-session',
            content: 'foreign sentinel',
        );
        $normalized = (new FakeWebChatAdapter)->receive($context, $message);
        app(ConversationChannelBridge::class)->resolveOrCreate((string) $tenant->id, $context, $normalized);

        return [$tenant, $actor];
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
