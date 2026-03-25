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

final class ResendWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_resend_delivered_webhook_updates_touchpoint_and_logs_event(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-resend-delivered');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Laura Rivera',
            'email' => 'laura@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up',
            'subject' => 'Seguimiento',
            'recipient_address' => 'laura@example.com',
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'resend',
            'provider_message_id' => 'email_123',
            'delivery_status' => 'sent',
            'opt_in_snapshot' => true,
            'sent_at' => now()->subMinute(),
        ]);

        config()->set('services.resend.webhook_secret', $secret = 'whsec_'.base64_encode('top-secret'));

        $payload = [
            'type' => 'email.delivered',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'email_id' => 'email_123',
                'tags' => [
                    ['name' => 'tenant_id', 'value' => (string) $tenant->getKey()],
                    ['name' => 'touchpoint_id', 'value' => (string) $touchpoint->getKey()],
                    ['name' => 'customer_profile_id', 'value' => (string) $profile->getKey()],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->postJson(
            route('webhooks.resend.email', [], false),
            $payload,
            $this->signedResendHeaders($jsonPayload, $secret, 'msg_delivered_1'),
        );

        $response->assertOk()->assertJson(['ok' => true, 'tracked' => true]);

        $touchpoint->refresh();

        $this->assertSame('delivered', $touchpoint->delivery_status);
        $this->assertNotNull($touchpoint->delivered_at);
        $this->assertSame(1, CustomerTouchpointEvent::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, InboundWebhook::query()->withoutGlobalScopes()->count());
    }

    public function test_resend_received_webhook_creates_crm_note_for_inbound_reply(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-resend-reply');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Carlos Mejia',
            'email' => 'carlos@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up',
            'subject' => 'Seguimiento',
            'recipient_address' => 'carlos@example.com',
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'resend',
            'provider_message_id' => 'email_456',
            'delivery_status' => 'sent',
            'opt_in_snapshot' => true,
            'sent_at' => now()->subMinute(),
        ]);

        config()->set('services.resend.webhook_secret', $secret = 'whsec_'.base64_encode('top-secret'));

        $payload = [
            'type' => 'email.received',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'email_id' => 'email_456',
                'text' => 'Si, quiero que me contacten manana por la tarde.',
                'tags' => [
                    ['name' => 'tenant_id', 'value' => (string) $tenant->getKey()],
                    ['name' => 'touchpoint_id', 'value' => (string) $touchpoint->getKey()],
                    ['name' => 'customer_profile_id', 'value' => (string) $profile->getKey()],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson(
            route('webhooks.resend.email', [], false),
            $payload,
            $this->signedResendHeaders($jsonPayload, $secret, 'msg_reply_1'),
        )->assertOk();

        $touchpoint->refresh();

        $this->assertSame('replied', $touchpoint->delivery_status);
        $this->assertNotNull($touchpoint->replied_at);
        $this->assertSame(1, CustomerNote::query()->withoutGlobalScopes()->count());
        $this->assertStringContainsString(
            'manana por la tarde',
            (string) CustomerNote::query()->withoutGlobalScopes()->first()->body,
        );
    }

    /**
     * @return array<string, string>
     */
    private function signedResendHeaders(string $payload, string $secret, string $messageId): array
    {
        $timestamp = (string) time();
        $rawSecret = base64_decode(substr($secret, strlen('whsec_')), true) ?: '';
        $signature = base64_encode(pack('H*', hash_hmac('sha256', "{$messageId}.{$timestamp}.{$payload}", $rawSecret)));

        return [
            'svix-id' => $messageId,
            'svix-timestamp' => $timestamp,
            'svix-signature' => "v1,{$signature}",
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
