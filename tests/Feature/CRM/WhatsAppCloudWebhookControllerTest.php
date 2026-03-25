<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\CustomerTouchpointEvent;
use App\Models\Payment\InboundWebhook;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class WhatsAppCloudWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_whatsapp_webhook_verification_returns_challenge(): void
    {
        config()->set('services.whatsapp_cloud.verify_token', 'verify-me');

        $this->get(route('webhooks.whatsapp-cloud.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'verify-me',
            'hub_challenge' => 'abc123',
        ], false))
            ->assertOk()
            ->assertSeeText('abc123');
    }

    public function test_whatsapp_status_webhook_updates_touchpoint_delivery_state(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-whatsapp-status');
        config()->set('services.whatsapp_cloud.app_secret', 'top-secret');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Paola Rivera',
            'phone' => '9999-1111',
            'preferred_channel' => 'whatsapp',
            'whatsapp_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up_whatsapp',
            'message' => 'Seguimiento por WhatsApp',
            'recipient_address' => $profile->phone,
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'whatsapp_cloud',
            'provider_message_id' => 'wamid.status.1',
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
            'opt_in_snapshot' => true,
            'sent_at' => now()->subMinute(),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.status.1',
                            'status' => 'read',
                            'timestamp' => (string) now()->timestamp,
                        ]],
                    ],
                ]],
            ]],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson(
            route('webhooks.whatsapp-cloud.handle', [], false),
            $payload,
            $this->signedWhatsAppHeaders($jsonPayload, 'top-secret'),
        )->assertOk()->assertJson([
            'ok' => true,
            'tracked' => 1,
        ]);

        $touchpoint->refresh();

        $this->assertSame(CustomerTouchpoint::DELIVERY_READ, $touchpoint->delivery_status);
        $this->assertNotNull($touchpoint->opened_at);
        $this->assertSame(1, CustomerTouchpointEvent::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, InboundWebhook::query()->withoutGlobalScopes()->count());
    }

    public function test_whatsapp_inbound_reply_creates_note_and_updates_opt_in(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-whatsapp-inbound');
        config()->set('services.whatsapp_cloud.app_secret', 'top-secret');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Rene Salgado',
            'phone' => '9999-2222',
            'preferred_channel' => 'whatsapp',
            'whatsapp_opt_in' => false,
            'marketing_opt_in' => false,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'automation_key' => 'reactivation_whatsapp',
            'message' => 'Queremos activar tu negocio otra vez.',
            'recipient_address' => $profile->phone,
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'whatsapp_cloud',
            'provider_message_id' => 'wamid.inbound.1',
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
            'opt_in_snapshot' => false,
            'sent_at' => now()->subMinute(),
        ]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.reply.1',
                            'from' => '50499992222',
                            'timestamp' => (string) now()->timestamp,
                            'text' => [
                                'body' => 'ALTA',
                            ],
                            'context' => [
                                'id' => 'wamid.inbound.1',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson(
            route('webhooks.whatsapp-cloud.handle', [], false),
            $payload,
            $this->signedWhatsAppHeaders($jsonPayload, 'top-secret'),
        )->assertOk()->assertJson([
            'ok' => true,
            'tracked' => 1,
        ]);

        $touchpoint->refresh();
        $profile->refresh();

        $this->assertSame(CustomerTouchpoint::DELIVERY_REPLIED, $touchpoint->delivery_status);
        $this->assertNotNull($touchpoint->replied_at);
        $this->assertTrue($profile->whatsapp_opt_in);
        $this->assertTrue($profile->marketing_opt_in);
        $this->assertSame('whatsapp', $profile->last_engaged_channel);
        $this->assertNotNull($profile->last_inbound_reply_at);
        $this->assertSame(2, CustomerNote::query()->withoutGlobalScopes()->count());
    }

    /**
     * @return array<string, string>
     */
    private function signedWhatsAppHeaders(string $payload, string $appSecret): array
    {
        $signature = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        return [
            'X-Hub-Signature-256' => $signature,
        ];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
