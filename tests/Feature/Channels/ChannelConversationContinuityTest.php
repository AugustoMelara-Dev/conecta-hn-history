<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelAuthorizationEnvelope;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ConversationChannelBridge;
use App\Application\Channels\NormalizedInboundMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $continued = $bridge->resolveOrCreate((string) $tenant->getKey(), $sms, $this->message($tenant, 'sms', 'sms-1', null, 'granted'));
        $withoutConsent = $bridge->resolveOrCreate((string) $tenant->getKey(), $email, $this->message($tenant, 'email', 'email-1', null, 'unknown'));

        self::assertSame($first, $continued);
        self::assertNotSame($first, $withoutConsent);
        self::assertSame(3, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenant->getKey())->count());
    }

    /** @return array{Tenant, User} */
    private function tenant(): array
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->pro()->create();
        $this->app->make(\App\Domain\Shared\Services\TenantContext::class)->setTenant((string) $tenant->getKey());

        return [$tenant, $actor];
    }

    private function context(Tenant $tenant, User $actor, string $channel): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->getKey(), channel: $channel, connectionKey: 'primary',
            marketCountryCode: 'HN', locale: 'es-HN', currency: 'HNL', timezone: 'America/Tegucigalpa',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->getKey(), (string) $actor->getKey()),
        );
    }

    private function message(Tenant $tenant, string $channel, string $event, ?string $thread, string $consent): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: $channel.'.adapter', tenantId: (string) $tenant->getKey(), channel: $channel,
            connectionKey: 'primary', externalMessageId: $event, externalThreadReference: $thread,
            normalizedIdentity: '+50499990000', identityType: 'phone', content: 'Continuemos.',
            attachments: [], consentStatus: $consent, deliveryState: 'delivered', providerEventId: $event,
            costAmount: '0.00', costCurrency: 'HNL', marketCountryCode: 'HN', locale: 'es-HN',
            timezone: 'America/Tegucigalpa',
        );
    }
}
