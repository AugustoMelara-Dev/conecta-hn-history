<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\NormalizedInboundMessage;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConversationChannelAuthorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_replay_and_continuation_use_the_active_private_canonical_context(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $firstMessage = $this->message($tenant, 'whatsapp', 'authority-in-001', 'authority-thread');
        $first = $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor, 'whatsapp'),
            $firstMessage,
        );

        self::assertSame(
            $first,
            $bridge->resolveOrCreate(
                (string) $tenant->id,
                $this->context($tenant, $actor, 'whatsapp'),
                $firstMessage,
            ),
        );

        $continued = $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor, 'sms'),
            $this->message($tenant, 'sms', 'authority-in-002', 'alternate-thread'),
            $first,
        );

        self::assertSame($first, $continued);
        self::assertSame(1, DB::table('ai_conversations')->where('tenant_id', $tenant->id)->count());
        self::assertSame(2, DB::table('ai_messages')->where('conversation_id', $first)->count());
        self::assertSame(2, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count());

        $conversation = DB::table('ai_conversations')->where('id', $first)->first();
        self::assertSame('tenant_private', (string) $conversation->visibility);
        self::assertSame('active', (string) $conversation->status);
        self::assertSame('whatsapp', (string) $conversation->origin_type);
        self::assertSame('es-HN', (string) $conversation->locale);
        self::assertSame('HN', (string) $conversation->market_country_code);
        self::assertSame('HNL', (string) $conversation->currency);
        self::assertSame('America/Tegucigalpa', (string) $conversation->timezone);
        self::assertSame(
            hash('sha256', 'authority-thread'),
            (string) $conversation->external_thread_hash,
        );
    }

    public function test_inbound_closed_and_public_rows_fail_closed_without_new_canonical_or_channel_writes(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $conversationId = $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor),
            $this->message($tenant, 'whatsapp', 'authority-in-negative-seed', 'authority-negative-thread'),
        );
        $messageCount = DB::table('ai_messages')->where('conversation_id', $conversationId)->count();
        $linkCount = DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count();

        DB::table('ai_conversations')->where('id', $conversationId)->update(['status' => 'closed']);
        $this->assertGenericAuthorization(fn () => $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor),
            $this->message($tenant, 'whatsapp', 'authority-in-closed', 'authority-closed-thread'),
            $conversationId,
        ));
        self::assertSame($messageCount, DB::table('ai_messages')->where('conversation_id', $conversationId)->count());
        self::assertSame($linkCount, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count());

        DB::table('ai_conversations')->where('id', $conversationId)->update([
            'status' => 'active',
            'visibility' => 'platform_public',
        ]);
        $this->assertGenericAuthorization(fn () => $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor),
            $this->message($tenant, 'whatsapp', 'authority-in-public', 'authority-public-thread'),
            $conversationId,
        ));
        self::assertSame($messageCount, DB::table('ai_messages')->where('conversation_id', $conversationId)->count());
        self::assertSame($linkCount, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count());
    }

    public function test_outbound_linkage_requires_an_active_private_conversation_for_the_current_tenant(): void
    {
        [$tenant, $actor] = $this->tenant();
        $bridge = app(ConversationChannelBridge::class);
        $ownerConversation = $bridge->resolveOrCreate(
            (string) $tenant->id,
            $this->context($tenant, $actor),
            $this->message($tenant, 'whatsapp', 'authority-out-seed', 'authority-out-thread'),
        );

        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $this->setTenant((string) $foreignTenant->id);
        $foreignConversation = $bridge->resolveOrCreate(
            (string) $foreignTenant->id,
            $this->context($foreignTenant, $foreignActor),
            $this->message($foreignTenant, 'whatsapp', 'authority-foreign-seed', 'authority-foreign-thread'),
        );
        $this->setTenant((string) $tenant->id);

        $valid = $this->outbound($tenant, 'authority-out-valid');
        $bridge->linkOutbound((string) $tenant->id, $ownerConversation, $valid);
        $ownerLinkCount = DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count();

        DB::table('ai_conversations')->where('id', $ownerConversation)->update(['status' => 'closed']);
        $this->assertGenericAuthorization(fn () => $bridge->linkOutbound(
            (string) $tenant->id,
            $ownerConversation,
            $this->outbound($tenant, 'authority-out-closed'),
        ));
        self::assertSame($ownerLinkCount, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count());

        DB::table('ai_conversations')->where('id', $ownerConversation)->update([
            'status' => 'active',
            'visibility' => 'platform_public',
        ]);
        $this->assertGenericAuthorization(fn () => $bridge->linkOutbound(
            (string) $tenant->id,
            $ownerConversation,
            $this->outbound($tenant, 'authority-out-public'),
        ));
        $this->assertGenericAuthorization(fn () => $bridge->linkOutbound(
            (string) $tenant->id,
            (string) Str::uuid(),
            $this->outbound($tenant, 'authority-out-missing'),
        ));
        $this->assertGenericAuthorization(fn () => $bridge->linkOutbound(
            (string) $tenant->id,
            $foreignConversation,
            $this->outbound($tenant, 'authority-out-foreign'),
        ));

        self::assertSame($ownerLinkCount, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->id)->count());
    }

    /** @return array{Tenant, User} */
    private function tenant(bool $setContext = true): array
    {
        $this->supportHondurasMarket();
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->pro()->create();
        if ($setContext) {
            $this->setTenant((string) $tenant->id);
        }

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

    private function setTenant(string $tenantId): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant($tenantId);
    }

    private function context(Tenant $tenant, User $actor, string $channel = 'whatsapp'): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: 'primary',
            marketCountryCode: 'HN',
            locale: 'es-HN',
            currency: 'HNL',
            timezone: 'America/Tegucigalpa',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->id, (string) $actor->id),
        );
    }

    private function message(
        Tenant $tenant,
        string $channel,
        string $event,
        ?string $thread,
        string $identity = '+50499990000',
    ): NormalizedInboundMessage {
        return new NormalizedInboundMessage(
            adapterKey: $channel.'.adapter',
            tenantId: (string) $tenant->id,
            channel: $channel,
            connectionKey: 'primary',
            externalMessageId: $event,
            externalThreadReference: $thread,
            normalizedIdentity: $identity,
            identityType: 'phone',
            content: 'Continuidad de conversación.',
            attachments: [],
            consentStatus: 'granted',
            deliveryState: 'delivered',
            providerEventId: $event,
            costAmount: '0.00',
            costCurrency: 'HNL',
            marketCountryCode: 'HN',
            locale: 'es-HN',
            timezone: 'America/Tegucigalpa',
        );
    }

    private function outbound(Tenant $tenant, string $idempotencyKey): ChannelOutboundMessage
    {
        return new ChannelOutboundMessage(
            tenantId: (string) $tenant->id,
            channel: 'whatsapp',
            connectionKey: 'primary',
            idempotencyKey: $idempotencyKey,
            externalThreadReference: 'authority-out-thread',
            recipient: '+50499990000',
            content: 'Seguimiento de la conversación.',
        );
    }

    private function assertGenericAuthorization(callable $operation): void
    {
        try {
            $operation();
            self::fail('The channel bridge should reject a non-active tenant-private conversation.');
        } catch (AuthorizationException $exception) {
            self::assertSame('Conversation does not belong to the tenant.', $exception->getMessage());
        }
    }
}
