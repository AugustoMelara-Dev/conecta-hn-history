<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelConflictException;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\NormalizedInboundMessage;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ChannelConversationContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_channel_continuity_requires_identity_and_granted_consent(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $whatsapp = $this->context($tenant, $actor, 'whatsapp');
        $sms = $this->context($tenant, $actor, 'sms');
        $email = $this->context($tenant, $actor, 'email');

        $first = $bridge->resolveOrCreate((string) $tenant->getKey(), $whatsapp, $this->message($tenant, 'whatsapp', 'wa-1', 'thread-1', 'granted'));
        $continued = $bridge->resolveOrCreate((string) $tenant->getKey(), $sms, $this->message($tenant, 'sms', 'sms-1', 'thread-2', 'granted'));
        $withoutConsent = $bridge->resolveOrCreate((string) $tenant->getKey(), $email, $this->message($tenant, 'email', 'email-1', null, 'unknown'));

        self::assertSame($first, $continued);
        self::assertNotSame($first, $withoutConsent);
        self::assertSame(3, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(
            hash('sha256', 'thread-1'),
            DB::table('ai_conversations')->where('id', $first)->value('external_thread_hash'),
        );
    }

    public function test_cross_channel_retry_uses_the_hash_matched_canonical_thread_after_an_alternate_link(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $first = $bridge->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'whatsapp'),
            $this->message($tenant, 'whatsapp', 'wa-canonical', 'thread-1', 'granted'),
        );

        $continued = $bridge->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'sms'),
            $this->message($tenant, 'sms', 'sms-alternate', 'thread-2', 'granted'),
        );
        self::assertSame($first, $continued);

        $outbound = new ChannelOutboundMessage(
            tenantId: (string) $tenant->getKey(),
            channel: 'email',
            connectionKey: 'primary',
            idempotencyKey: 'outbound-alternate',
            externalThreadReference: 'thread-3',
            recipient: 'owner@example.test',
            content: 'Seguimiento.',
            metadata: ['conversation_id' => $first],
        );
        $bridge->linkOutbound((string) $tenant->getKey(), $first, $outbound);

        $retry = $bridge->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'sms'),
            $this->message($tenant, 'sms', 'sms-retry', 'thread-4', 'granted'),
        );

        self::assertSame($first, $retry);
        self::assertSame(
            hash('sha256', 'thread-1'),
            DB::table('ai_conversations')->where('id', $first)->value('external_thread_hash'),
        );
        self::assertSame(3, DB::table('ai_messages')->where('conversation_id', $first)->count());
    }

    public function test_orphaned_webhook_claim_rejects_a_different_payload_hash(): void
    {
        [$tenant, $actor] = $this->tenant();
        $event = 'orphaned-event';
        $payloadA = hash('sha256', 'payload-a');
        DB::table('channel_webhook_claims')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_event_id' => $event,
            'payload_hash' => $payloadA,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ChannelConflictException::class);
        app(ConversationChannelBridge::class)->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'whatsapp'),
            $this->message(
                $tenant,
                'whatsapp',
                $event,
                'orphan-thread',
                'granted',
                ['webhook_payload_hash' => hash('sha256', 'payload-b')],
            ),
        );
    }

    public function test_orphaned_webhook_claim_with_the_same_payload_hash_repairs_the_lineage(): void
    {
        [$tenant, $actor] = $this->tenant();
        $event = 'repairable-event';
        $payloadHash = hash('sha256', 'payload-repairable');
        DB::table('channel_webhook_claims')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'channel' => 'whatsapp',
            'connection_key' => 'primary',
            'provider_event_id' => $event,
            'payload_hash' => $payloadHash,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conversationId = app(ConversationChannelBridge::class)->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'whatsapp'),
            $this->message(
                $tenant,
                'whatsapp',
                $event,
                'repair-thread',
                'granted',
                ['webhook_payload_hash' => $payloadHash],
            ),
        );

        self::assertSame(
            $conversationId,
            DB::table('dovra_conversation_channel_links')
                ->where('tenant_id', $tenant->getKey())
                ->where('provider_event_id', $event)
                ->value('conversation_id'),
        );
    }

    public function test_outbound_lineage_replay_is_stable_and_payload_conflicts_are_rejected(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $conversationId = $bridge->resolveOrCreate(
            (string) $tenant->getKey(),
            $this->context($tenant, $actor, 'whatsapp'),
            $this->message($tenant, 'whatsapp', 'wa-outbound-seed', 'thread-outbound', 'granted'),
        );
        $message = new ChannelOutboundMessage(
            tenantId: (string) $tenant->getKey(),
            channel: 'whatsapp',
            connectionKey: 'primary',
            idempotencyKey: 'outbound-1',
            externalThreadReference: 'thread-outbound',
            recipient: '+50499990000',
            content: 'Tu solicitud fue recibida.',
            metadata: ['conversation_id' => $conversationId],
        );
        $bridge->linkOutbound((string) $tenant->getKey(), $conversationId, $message);
        $bridge->linkOutbound((string) $tenant->getKey(), $conversationId, $message);

        $this->expectException(ChannelConflictException::class);
        $bridge->linkOutbound(
            (string) $tenant->getKey(),
            $conversationId,
            new ChannelOutboundMessage(
                tenantId: (string) $tenant->getKey(), channel: 'whatsapp', connectionKey: 'primary',
                idempotencyKey: 'outbound-1', externalThreadReference: 'thread-outbound',
                recipient: '+50499990000', content: 'Mensaje diferente.', metadata: ['conversation_id' => $conversationId],
            ),
        );
    }

    /** @return array{Tenant, User} */
    private function tenant(): array
    {
        $this->supportHondurasMarket();
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->pro()->create();
        $this->app->make(TenantContext::class)->setTenant((string) $tenant->getKey());

        return [$tenant, $actor];
    }

    private function supportHondurasMarket(): void
    {
        $now = now();
        DB::table('countries')->updateOrInsert(
            ['code' => 'HN'],
            [
                'name' => 'Honduras',
                'native_name' => 'Honduras',
                'calling_code' => '+504',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('currencies')->updateOrInsert(
            ['code' => 'HNL'],
            [
                'name' => 'Lempira',
                'symbol' => 'L',
                'minor_unit' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('locales')->updateOrInsert(
            ['code' => 'es-HN'],
            [
                'name' => 'Spanish (Honduras)',
                'native_name' => 'Español (Honduras)',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('timezones')->updateOrInsert(
            ['name' => 'America/Tegucigalpa'],
            [
                'label' => 'Tegucigalpa',
                'utc_offset' => '-06:00',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function context(Tenant $tenant, User $actor, string $channel): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->getKey(), channel: $channel, connectionKey: 'primary',
            marketCountryCode: 'HN', locale: 'es-HN', currency: 'HNL', timezone: 'America/Tegucigalpa',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->getKey(), (string) $actor->getKey()),
        );
    }

    private function message(
        Tenant $tenant,
        string $channel,
        string $event,
        ?string $thread,
        string $consent,
        array $metadata = [],
    ): NormalizedInboundMessage {
        return new NormalizedInboundMessage(
            adapterKey: $channel.'.adapter', tenantId: (string) $tenant->getKey(), channel: $channel,
            connectionKey: 'primary', externalMessageId: $event, externalThreadReference: $thread,
            normalizedIdentity: '+50499990000', identityType: 'phone', content: 'Continuemos.',
            attachments: [], consentStatus: $consent, deliveryState: 'delivered', providerEventId: $event,
            costAmount: '0.00', costCurrency: 'HNL', marketCountryCode: 'HN', locale: 'es-HN',
            timezone: 'America/Tegucigalpa', metadata: $metadata,
        );
    }
}
