<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Domain\TenantGovernance\Services\CustomerMessagingGateway;
use App\Mail\CustomerLifecycleMail;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CustomerAutomationManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_email_follow_up_is_sent_and_logged(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-automation-cafe');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 30,
        ]);

        $touchpoint = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento de Cafe Norte',
            body: 'Queremos ayudarte a volver a comprar.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertNotNull($touchpoint->sent_at);
        $this->assertSame('ana@example.com', $touchpoint->recipient_address);
        $this->assertNotNull($profile->fresh()->last_contacted_at);

        Mail::assertSent(CustomerLifecycleMail::class, function (CustomerLifecycleMail $mail): bool {
            return $mail->hasTo('ana@example.com');
        });
    }

    public function test_reactivation_batch_respects_marketing_opt_in_and_logs_outcomes(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-automation-reactivation');

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente Activo',
            'email' => 'activo@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 92,
        ]);

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente Sin Marketing',
            'email' => 'sin-marketing@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => false,
            'reactivation_score' => 88,
        ]);

        $result = app(CustomerAutomationManager::class)->runSuggestedAutomationBatch(
            tenant: $tenant,
            automationKey: 'reactivation_email',
            actorUserId: (string) $user->getKey(),
            limit: 10,
        );

        $this->assertSame([
            'processed' => 2,
            'sent' => 1,
            'skipped' => 1,
            'failed' => 0,
        ], $result);

        $this->assertSame(2, CustomerTouchpoint::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, CustomerTouchpoint::query()->withoutGlobalScopes()->where('status', CustomerTouchpoint::STATUS_SENT)->count());
        $this->assertSame(1, CustomerTouchpoint::query()->withoutGlobalScopes()->where('status', CustomerTouchpoint::STATUS_SKIPPED)->count());

        Mail::assertSent(CustomerLifecycleMail::class, 1);
    }

    public function test_daily_template_limit_skips_duplicate_send_for_same_customer(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-automation-limit');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana-limit@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $first = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento inicial',
            body: 'Primer toque.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $second = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento duplicado',
            body: 'Segundo toque.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $first->status);
        $this->assertSame(CustomerTouchpoint::STATUS_SKIPPED, $second->status);
        $this->assertSame('manual_follow_up', $first->template_key);
        $this->assertSame('manual_follow_up', $second->template_key);
        $this->assertStringContainsString('template hoy', (string) $second->last_error);

        Mail::assertSent(CustomerLifecycleMail::class, 1);
    }

    public function test_gateway_metadata_is_persisted_when_external_provider_returns_message_id(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-automation-provider');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Maria Gomez',
            'email' => 'maria@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 12,
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')

            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'email_123',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);

        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $touchpoint = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento premium',
            body: 'Queremos ayudarte a cerrar tu siguiente compra.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $this->assertSame('resend', $touchpoint->provider);
        $this->assertSame('email_123', $touchpoint->provider_message_id);
        $this->assertSame('sent', $touchpoint->delivery_status);
        $this->assertSame('sent', data_get($touchpoint->provider_payload, 'last_event'));
    }

    public function test_failed_conversation_sync_does_not_overwrite_sent_touchpoint_status(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-automation-sync-warning');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Maria Gomez',
            'email' => 'maria-sync@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')
            ->once()
            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'email_sync_123',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);

        $conversationOps = \Mockery::mock(CustomerConversationOpsManager::class);
        $conversationOps->shouldReceive('markOutboundResponse')
            ->once()
            ->andThrow(new \RuntimeException('CRM sync downstream timeout'));

        $this->app->instance(CustomerMessagingGateway::class, $gateway);
        $this->app->instance(CustomerConversationOpsManager::class, $conversationOps);

        $touchpoint = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento premium',
            body: 'Queremos ayudarte a cerrar tu siguiente compra.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertNull($touchpoint->failed_at);
        $this->assertNull($touchpoint->next_retry_at);
        $this->assertSame('sent', $touchpoint->delivery_status);
        $this->assertSame('conversation', data_get($touchpoint->metadata, 'sync_warnings.0.scope'));
        $this->assertStringContainsString('CRM sync downstream timeout', (string) data_get($touchpoint->metadata, 'sync_warnings.0.message'));
    }

    public function test_whatsapp_follow_up_is_sent_and_logged_with_provider_metadata(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-automation-whatsapp');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Laura Pineda',
            'phone' => '9999-1111',
            'preferred_channel' => 'whatsapp',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
            'whatsapp_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 12,
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleWhatsApp')

            ->andReturn([
                'provider' => 'whatsapp_cloud',
                'provider_message_id' => 'wamid.123',
                'delivery_status' => 'sent',
                'payload' => ['messages' => [['id' => 'wamid.123']]],
            ]);

        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $touchpoint = app(CustomerAutomationManager::class)->sendSuggestedAutomation(
            profile: $profile,
            automationKey: 'manual_follow_up_whatsapp',
            actorUserId: (string) $user->getKey(),
            channel: 'whatsapp',
        );

        $this->assertSame('whatsapp', $touchpoint->channel);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertSame('whatsapp_cloud', $touchpoint->provider);
        $this->assertSame('wamid.123', $touchpoint->provider_message_id);
        $this->assertSame('sent', $touchpoint->delivery_status);
        $this->assertSame('manual_follow_up_whatsapp', $touchpoint->automation_key);
        $this->assertSame('whatsapp', $profile->fresh()->last_engaged_channel);
    }

    public function test_retry_failed_email_touchpoint_reuses_same_record_and_clears_retry_queue(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-automation-retry');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Maria Gomez',
            'email' => 'maria-retry@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => (int) $profile->getKey(),
            'created_by_user_id' => (string) $user->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up',
            'template_key' => 'manual_follow_up',
            'subject' => 'Seguimiento premium',
            'message' => 'Queremos ayudarte a cerrar tu siguiente compra.',
            'recipient_address' => 'maria-retry@example.com',
            'status' => CustomerTouchpoint::STATUS_FAILED,
            'delivery_status' => CustomerTouchpoint::DELIVERY_FAILED,
            'attempt_count' => 1,
            'retry_limit' => 2,
            'opt_in_snapshot' => true,
            'last_attempt_at' => now()->subMinutes(30),
            'failed_at' => now()->subMinutes(30),
            'next_retry_at' => now()->subMinute(),
            'metadata' => [
                'daily_limit' => 1,
                'retry_backoff_minutes' => 30,
            ],
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')

            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'retry_email_123',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);

        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $retried = app(CustomerAutomationManager::class)->retryTouchpoint($touchpoint, (string) $user->getKey());

        $this->assertSame((int) $touchpoint->getKey(), (int) $retried->getKey());
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $retried->status);
        $this->assertSame(2, $retried->attempt_count);
        $this->assertNull($retried->next_retry_at);
        $this->assertSame('retry_email_123', $retried->provider_message_id);
        $this->assertNotNull($retried->sent_at);
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
