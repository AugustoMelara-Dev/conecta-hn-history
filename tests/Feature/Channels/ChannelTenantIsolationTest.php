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
use Illuminate\Support\Str;
use Tests\TestCase;

final class ChannelTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_b_cannot_resolve_tenant_a_conversation_or_write_a_link(): void
    {
        [$tenantA, $actorA] = $this->tenant();
        [$tenantB, $actorB] = $this->tenant(false);
        $contextA = $this->context($tenantA, $actorA);
        $messageB = $this->message($tenantB, 'event-b');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(ConversationChannelBridge::class)->resolveOrCreate((string) $tenantA->getKey(), $contextA, $messageB, (string) Str::uuid());

        self::assertSame(0, DB::table('dovra_conversation_channel_links')->where('tenant_id', $tenantB->getKey())->count());
    }

    /** @return array{Tenant, User} */
    private function tenant(bool $setContext = true): array
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->forUser($actor)->pro()->create();
        if ($setContext) {
            $this->app->make(\App\Domain\Shared\Services\TenantContext::class)->reset();
            $this->app->make(\App\Domain\Shared\Services\TenantContext::class)->setTenant((string) $tenant->getKey());
        }

        return [$tenant, $actor];
    }

    private function context(Tenant $tenant, User $actor): ChannelContext
    {
        return new ChannelContext(
            tenantId: (string) $tenant->getKey(), channel: 'whatsapp', connectionKey: 'primary',
            marketCountryCode: 'HN', locale: 'es-HN', currency: 'HNL', timezone: 'America/Tegucigalpa',
            authorization: new ChannelAuthorizationEnvelope((string) $tenant->getKey(), (string) $actor->getKey()),
        );
    }

    private function message(Tenant $tenant, string $event): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: 'whatsapp.adapter', tenantId: (string) $tenant->getKey(), channel: 'whatsapp',
            connectionKey: 'primary', externalMessageId: $event, externalThreadReference: 'thread',
            normalizedIdentity: '+50499990000', identityType: 'phone', content: 'No.', attachments: [],
            consentStatus: 'granted', deliveryState: 'delivered', providerEventId: $event,
            costAmount: '0.00', costCurrency: 'HNL', marketCountryCode: 'HN', locale: 'es-HN',
            timezone: 'America/Tegucigalpa',
        );
    }
}
