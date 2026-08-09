<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\NormalizedInboundMessage;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ConversationChannelBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_channel_thread_is_replay_safe_and_resolves_one_conversation(): void
    {
        [$tenant, $actor] = $this->tenant();
        $context = $this->context($tenant, $actor, 'whatsapp');
        $message = $this->message($tenant, 'whatsapp', 'event-001', 'thread-1', 'granted');

        $first = app(ConversationChannelBridge::class)->resolveOrCreate((string) $tenant->getKey(), $context, $message);
        $second = app(ConversationChannelBridge::class)->resolveOrCreate((string) $tenant->getKey(), $context, $message);

        self::assertSame($first, $second);
        self::assertSame(1, DB::table('ai_conversations')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(1, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->getKey())->count());
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
            tenantId: (string) $tenant->getKey(),
            channel: $channel,
            connectionKey: 'primary',
            marketCountryCode: 'HN',
            locale: 'es-HN',
            currency: 'HNL',
            timezone: 'America/Tegucigalpa',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->getKey(), (string) $actor->getKey()),
        );
    }

    private function message(Tenant $tenant, string $channel, string $event, string $thread, string $consent): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: $channel.'.adapter',
            tenantId: (string) $tenant->getKey(),
            channel: $channel,
            connectionKey: 'primary',
            externalMessageId: $event,
            externalThreadReference: $thread,
            normalizedIdentity: '+50499990000',
            identityType: 'phone',
            content: 'Necesito ayuda con mi pedido.',
            attachments: [],
            consentStatus: $consent,
            deliveryState: 'delivered',
            providerEventId: $event,
            costAmount: '0.00',
            costCurrency: 'HNL',
            marketCountryCode: 'HN',
            locale: 'es-HN',
            timezone: 'America/Tegucigalpa',
        );
    }
}
