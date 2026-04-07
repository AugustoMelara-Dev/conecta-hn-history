<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Domain\TenantGovernance\Services\CustomerMessagingGateway;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerLifecycleTriggerManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_repeat_purchase_orders_dispatch_whatsapp_reward_for_repeat_customer(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-lifecycle-repeat');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Andrea Flores',
            'phone' => '9999-5555',
            'preferred_channel' => 'whatsapp',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'loyal',
            'orders_count' => 2,
            'repeat_purchase_count' => 1,
            'whatsapp_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 5,
        ]);

        $order = Order::withoutEvents(function () use ($tenant, $profile): Order {
            return Order::factory()
                ->forTenant($tenant)
                ->delivered()
                ->create([
                    'customer_profile_id' => $profile->getKey(),
                    'customer_name' => $profile->name,
                    'customer_phone' => $profile->phone,
                    'total' => 990.00,
                    'subtotal' => 990.00,
                    'discount' => 0.00,
                    'delivery_fee' => 0.00,
                ]);
        });

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleWhatsApp')

            ->andReturn([
                'provider' => 'whatsapp_cloud',
                'provider_message_id' => 'wamid.repeat.1',
                'delivery_status' => 'sent',
                'payload' => ['messages' => [['id' => 'wamid.repeat.1']]],
            ]);
        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $touchpoint = app(CustomerLifecycleTriggerManager::class)->handleOrderSaved($order);

        $this->assertInstanceOf(CustomerTouchpoint::class, $touchpoint);
        $this->assertSame('repeat_purchase_reward_whatsapp', $touchpoint->automation_key);
        $this->assertSame('whatsapp', $touchpoint->channel);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
    }

    public function test_appointment_follow_up_prefers_email_when_whatsapp_is_not_allowed(): void
    {
        [, $tenant] = $this->makeTenantOwner('crm-lifecycle-appointment');

        $service = Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Revision especializada',
        ]);

        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Agenda Norte',
        ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Carlos Pinto',
            'email' => 'carlos@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'booked_service',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'whatsapp_opt_in' => false,
            'reactivation_score' => 10,
        ]);

        $appointment = Appointment::withoutEvents(function () use ($tenant, $service, $provider, $profile): Appointment {
            return Appointment::query()->create([
                'tenant_id' => $tenant->getKey(),
                'service_id' => $service->getKey(),
                'service_provider_id' => $provider->getKey(),
                'user_id' => null,
                'customer_profile_id' => $profile->getKey(),
                'scheduled_at' => now()->addDays(2),
                'end_at' => now()->addDays(2)->addHour(),
                'status' => Appointment::STATUS_CONFIRMED,
                'customer_name' => $profile->name,
                'customer_email' => $profile->email,
            ]);
        });

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')

            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'email.appointment.1',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);
        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $touchpoint = app(CustomerLifecycleTriggerManager::class)->handleAppointmentSaved($appointment);

        $this->assertInstanceOf(CustomerTouchpoint::class, $touchpoint);
        $this->assertSame('appointment_follow_up_email', $touchpoint->automation_key);
        $this->assertSame('email', $touchpoint->channel);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
    }

    public function test_reactivation_sweep_updates_last_reactivated_at_for_sent_profiles(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-lifecycle-reactivation');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Maria Carias',
            'email' => 'maria@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 88,
            'last_seen_at' => now()->subDays(75),
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')

            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'email.reactivation.1',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);
        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $result = app(CustomerLifecycleTriggerManager::class)->runReactivationSweep(
            tenant: $tenant,
            actorUserId: (string) $user->getKey(),
            limit: 10,
        );

        $this->assertSame([
            'processed' => 1,
            'sent' => 1,
            'skipped' => 0,
            'failed' => 0,
        ], $result);
        $this->assertNotNull($profile->fresh()->last_reactivated_at);
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
