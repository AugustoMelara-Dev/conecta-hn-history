<?php

declare(strict_types=1);

namespace Tests\Support\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use App\Infrastructure\Channels\Email\FakeEmailAdapter;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GlobalMarketFixture;

/**
 * Test-only topology, guard, and count helpers for the Program 07 composite.
 */
final class Program07CompositeCertificateFixture
{
    /** @var list<string> */
    private const TENANT_TABLES = [
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

    /**
     * @return array{
     *   subject_tenant:string,
     *   subject_actor:string,
     *   foreign_tenant:string,
     *   foreign_actor:string,
     *   email_secret:string,
     *   whatsapp_secret:string
     * }
     */
    public static function create(): array
    {
        self::assertGuard();

        /** @var array{0:Tenant,1:User,2:Tenant,3:User} $topology */
        $topology = Model::withoutEvents(function (): array {
            $subjectActor = User::factory()->create([
                'name' => 'Program 07 Subject Actor',
                'email' => 'program07-subject@example.test',
            ]);
            $subject = Tenant::factory()->forUser($subjectActor)->pro()->create([
                'name' => 'Program 07 Subject Tenant',
            ]);
            $subjectActor->forceFill(['tenant_id' => $subject->getKey()])->save();

            $foreignActor = User::factory()->create([
                'name' => 'Program 07 Foreign Actor',
                'email' => 'program07-foreign@example.test',
            ]);
            $foreign = Tenant::factory()->forUser($foreignActor)->pro()->create([
                'name' => 'Program 07 Foreign Tenant',
            ]);
            $foreignActor->forceFill(['tenant_id' => $foreign->getKey()])->save();

            GlobalMarketFixture::assignCanada($subject);
            GlobalMarketFixture::assignCanada($foreign);

            return [$subject, $subjectActor, $foreign, $foreignActor];
        });

        $subjectTenant = (string) $topology[0]->getKey();
        $subjectActor = (string) $topology[1]->getKey();
        $foreignTenant = (string) $topology[2]->getKey();
        $foreignActor = (string) $topology[3]->getKey();
        $emailSecret = 'program07-email-webhook-secret';
        $whatsappSecret = 'program07-whatsapp-webhook-secret';

        self::setTenant($foreignTenant);
        $context = new ChannelContext(
            tenantId: $foreignTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            marketCountryCode: 'CA',
            locale: 'en-CA',
            currency: 'CAD',
            timezone: 'America/Toronto',
            connection: ['secret_reference_key' => 'program07.email.foreign', 'provider' => 'email.fake'],
            authorization: new ChannelAuthorizationEnvelope($foreignTenant, $foreignActor),
        );
        $payload = json_encode([
            'event' => 'message.received',
            'id' => 'program07-foreign-event',
            'subject' => 'sentinel',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $unsigned = new ChannelInboundMessage(
            tenantId: $foreignTenant,
            channel: 'email',
            connectionKey: 'email-primary',
            externalMessageId: 'program07-foreign-message',
            externalThreadReference: 'program07-foreign-thread',
            identityType: 'email',
            identityValue: 'buyer@example.test',
            content: 'Foreign sentinel.',
            consentStatus: 'granted',
            webhookEventId: 'program07-foreign-event',
            webhookPayload: $payload,
            webhookTimestamp: '2026-08-03T15:00:00Z',
            webhookReplayKey: 'program07-foreign-replay',
        );
        $previousNow = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03T15:00:00Z', 'UTC'));
        try {
            $normalized = (new FakeEmailAdapter($emailSecret))->receive($context, self::signedMessage($unsigned, $emailSecret));
        } finally {
            CarbonImmutable::setTestNow($previousNow);
        }
        app(ConversationChannelBridge::class)->resolveOrCreate($foreignTenant, $context, $normalized);

        self::setTenant($subjectTenant);

        return [
            'subject_tenant' => $subjectTenant,
            'subject_actor' => $subjectActor,
            'foreign_tenant' => $foreignTenant,
            'foreign_actor' => $foreignActor,
            'email_secret' => $emailSecret,
            'whatsapp_secret' => $whatsappSecret,
        ];
    }

    /** @return array<string, int> */
    public static function tenantCounts(string $tenantId): array
    {
        $counts = [];
        foreach (self::TENANT_TABLES as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenantId)->count();
        }

        return $counts;
    }

    /** @return array<string, int> */
    public static function externalCounts(): array
    {
        return [
            'payments' => DB::table('payments')->count(),
            'subscription_payments' => DB::table('subscription_payments')->count(),
            'webhook_logs' => DB::table('webhook_logs')->count(),
            'outbox_messages' => DB::table('outbox_messages')->count(),
        ];
    }

    /** @param array<string, int> $before @param array<string, int> $after @return array<string, int> */
    public static function delta(array $before, array $after): array
    {
        $delta = [];
        foreach ($before as $key => $value) {
            $delta[$key] = (int) ($after[$key] ?? 0) - (int) $value;
        }

        return $delta;
    }

    /** @param array<string, mixed> $state */
    public static function hashState(array $state): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }

            return $value;
        };

        return hash('sha256', json_encode(
            $normalize($state),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function assertGuard(): void
    {
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException('Program 07 composite fixture requires testing environment.');
        }
        if (strtolower((string) getenv('DOVRA_PROGRAM07_COMPOSITE_ALLOW')) !== 'true') {
            throw new \RuntimeException('Program 07 composite fixture guard is disabled.');
        }
        if (strtolower((string) getenv('DB_CONNECTION')) !== 'sqlite'
            || strtolower((string) config('database.default')) !== 'sqlite') {
            throw new \RuntimeException('Program 07 composite fixture requires SQLite.');
        }
        if (strtolower((string) getenv('CACHE_STORE')) !== 'array'
            || strtolower((string) getenv('SESSION_DRIVER')) !== 'array') {
            throw new \RuntimeException('Program 07 composite fixture requires array cache and session stores.');
        }

        $database = (string) getenv('DB_DATABASE');
        $declaredDatabase = (string) getenv('DOVRA_PROGRAM07_COMPOSITE_DATABASE');
        $configuredDatabase = (string) config('database.connections.sqlite.database');
        if (! self::isAbsolute($database)
            || ! self::isRegularFile($database)
            || ! self::samePath($database, $declaredDatabase)
            || ! self::samePath($database, $configuredDatabase)
            || ! self::samePath($database, (string) getenv('DOVRA_PROGRAM07_COMPOSITE_DATABASE'))
            || self::isAlias($database)) {
            throw new \RuntimeException('Program 07 composite fixture requires one exact disposable SQLite database path.');
        }

        $databaseParent = self::canonical(dirname($database));
        $report = (string) getenv('DOVRA_PROGRAM07_COMPOSITE_REPORT');
        $cache = (string) getenv('APP_CONFIG_CACHE');
        foreach ([['report', $report], ['cache', $cache]] as [$label, $path]) {
            if (! self::isAbsolute($path)
                || file_exists($path)
                || is_link($path)
                || ! self::samePath(dirname($path), $databaseParent)
                || self::isAlias(dirname($path))) {
                throw new \RuntimeException('Program 07 composite fixture requires owned nonexistent '.$label.' path.');
            }
        }
        if (! self::samePath(dirname($report), dirname($cache))) {
            throw new \RuntimeException('Program 07 composite fixture requires sibling report and cache paths.');
        }
    }

    private static function setTenant(string $tenantId): void
    {
        $context = app(\App\Domain\Shared\Services\TenantContext::class);
        $context->reset();
        $context->setTenant($tenantId);
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

    private static function isRegularFile(string $path): bool
    {
        if (! is_file($path) || is_link($path)) {
            return false;
        }

        $canonical = realpath($path);

        return is_string($canonical) && self::samePath($canonical, $path);
    }

    private static function isAlias(string $path): bool
    {
        $cursor = $path;
        while (true) {
            if (is_link($cursor)) {
                return true;
            }
            $real = realpath($cursor);
            if ($real !== false && ! self::samePath($real, $cursor)) {
                return true;
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return false;
            }
            $cursor = $parent;
        }
    }

    private static function canonical(string $path): string
    {
        $real = realpath($path);

        return is_string($real) ? $real : $path;
    }

    private static function samePath(string $left, string $right): bool
    {
        if (! self::isAbsolute($left) || ! self::isAbsolute($right)) {
            return false;
        }

        $leftCanonical = self::canonical($left);
        $rightCanonical = self::canonical($right);

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($leftCanonical, $rightCanonical) === 0
            : $leftCanonical === $rightCanonical;
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
