<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerProfileManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_profile_aggregates_orders_appointments_and_leads(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-cafe-norte');

        Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'source' => 'website',
            'status' => 'new',
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Consulta premium',
        ]);

        LoyaltyProgram::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Club Norte',
            'earn_ratio' => 10,
            'redemption_ratio' => 100,
            'expiry_days' => 365,
            'is_active' => true,
        ]);

        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Equipo Norte',
        ]);

        $appointment = Appointment::withoutEvents(function () use ($tenant, $service, $provider): Appointment {
            return Appointment::query()->create([
                'tenant_id' => $tenant->getKey(),
                'service_id' => $service->getKey(),
                'service_provider_id' => $provider->getKey(),
                'user_id' => null,
                'scheduled_at' => now()->addDays(2),
                'end_at' => now()->addDays(2)->addHour(),
                'status' => Appointment::STATUS_CONFIRMED,
                'customer_name' => 'Ana Castillo',
                'customer_email' => 'ana@example.com',
                'customer_phone' => '9999-1111',
            ]);
        });

        app(\App\Domain\TenantGovernance\Services\CustomerProfileManager::class)->syncAppointment($appointment);

        $order = Order::factory()
            ->forTenant($tenant)
            ->delivered()
            ->create([
                'user_id' => $user->getKey(),
                'customer_name' => 'Ana Castillo',
                'customer_email' => 'ana@example.com',
                'customer_phone' => '9999-1111',
                'total' => 840.00,
                'subtotal' => 840.00,
                'discount' => 0.00,
                'delivery_fee' => 0.00,
                'source' => 'whatsapp',
            ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame('Ana Castillo', $profile->name);
        $this->assertSame('ana@example.com', $profile->email);
        $this->assertSame(1, $profile->orders_count);
        $this->assertSame(1, $profile->appointments_count);
        $this->assertSame(1, $profile->leads_count);
        $this->assertSame('active', $profile->lifecycle_stage);
        $this->assertSame('booked_service', $profile->customer_segment);
        $this->assertSame('website', $profile->acquisition_channel);
        $this->assertSame('whatsapp', $profile->first_conversion_channel);
        $this->assertSame('840.00', $profile->first_conversion_value);
        $this->assertSame('840.00', $profile->initial_ltv);
        $this->assertSame('840.00', $profile->total_spent);
        $this->assertSame(0, $profile->repeat_purchase_count);
        $this->assertSame(84, $profile->loyalty_points_balance);
        $this->assertSame('base', $profile->loyalty_tier);
        $this->assertNotNull($profile->last_seen_at);
        $this->assertSame((int) $profile->getKey(), (int) $order->fresh()->customer_profile_id);
    }

    public function test_customer_profiles_merge_when_phone_only_and_email_only_records_join_later(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-merge-norte');

        Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Carlos',
            'email' => null,
            'phone' => '9888-0000',
            'source' => 'manual',
            'status' => 'new',
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);

        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->getKey(),
        ]);

        $appointment = Appointment::withoutEvents(function () use ($tenant, $service, $provider): Appointment {
            return Appointment::query()->create([
                'tenant_id' => $tenant->getKey(),
                'service_id' => $service->getKey(),
                'service_provider_id' => $provider->getKey(),
                'user_id' => null,
                'scheduled_at' => now()->addDay(),
                'end_at' => now()->addDay()->addMinutes(45),
                'status' => Appointment::STATUS_PENDING,
                'customer_name' => 'Carlos Romero',
                'customer_email' => 'carlos@example.com',
                'customer_phone' => null,
            ]);
        });

        app(\App\Domain\TenantGovernance\Services\CustomerProfileManager::class)->syncAppointment($appointment);

        $this->assertSame(2, CustomerProfile::query()->withoutGlobalScopes()->count());

        Order::factory()
            ->forTenant($tenant)
            ->confirmed()
            ->create([
                'user_id' => $user->getKey(),
                'customer_name' => 'Carlos Romero',
                'customer_email' => 'carlos@example.com',
                'customer_phone' => '9888-0000',
                'total' => 1200.00,
                'subtotal' => 1200.00,
                'discount' => 0.00,
                'delivery_fee' => 0.00,
            ]);

        $this->assertSame(1, CustomerProfile::query()->withoutGlobalScopes()->count());

        $profile = CustomerProfile::query()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame('carlos@example.com', $profile->email);
        $this->assertSame(1, $profile->orders_count);
        $this->assertSame(1, $profile->appointments_count);
        $this->assertSame(1, $profile->leads_count);
        $this->assertSame('manual', $profile->acquisition_channel);
        $this->assertSame('1200.00', $profile->initial_ltv);
        $this->assertSame(0, $profile->repeat_purchase_count);
    }

    public function test_manual_merge_moves_customer_workspace_into_single_profile(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-manual-merge');

        $primary = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'repeat',
            'marketing_opt_in' => true,
        ]);

        $duplicate = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana C.',
            'phone' => '9999-1111',
            'preferred_channel' => 'whatsapp',
            'customer_segment' => 'reactivation',
            'whatsapp_opt_in' => true,
            'crm_notes' => 'Cliente contactada por WhatsApp.',
        ]);

        $order = Order::withoutEvents(function () use ($tenant, $user, $duplicate): Order {
            return Order::factory()
                ->forTenant($tenant)
                ->delivered()
                ->create([
                    'user_id' => $user->getKey(),
                    'customer_profile_id' => $duplicate->getKey(),
                    'customer_name' => 'Ana Castillo',
                    'customer_email' => 'ana@example.com',
                    'customer_phone' => '9999-1111',
                    'total' => 640.00,
                    'subtotal' => 640.00,
                    'discount' => 0.00,
                    'delivery_fee' => 0.00,
                ]);
        });

        $note = CustomerNote::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $duplicate->getKey(),
            'assigned_user_id' => $user->getKey(),
            'note_type' => 'follow_up',
            'status' => 'open',
            'priority' => 'high',
            'source_channel' => 'whatsapp',
            'requires_follow_up' => true,
            'body' => 'Confirmar recompra esta semana.',
        ]);

        $task = CustomerTask::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $duplicate->getKey(),
            'customer_note_id' => $note->getKey(),
            'assigned_user_id' => $user->getKey(),
            'task_type' => 'reply_follow_up',
            'title' => 'Responder a Ana',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $duplicate->getKey(),
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up_whatsapp',
            'message' => 'Seguimos pendientes de tu compra.',
            'recipient_address' => '9999-1111',
            'status' => CustomerTouchpoint::STATUS_SENT,
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
        ]);

        $merged = app(\App\Domain\TenantGovernance\Services\CustomerProfileManager::class)->mergeProfiles(
            $primary,
            $duplicate,
            (string) $user->getKey(),
        );

        $this->assertSame(1, CustomerProfile::query()->withoutGlobalScopes()->count());
        $this->assertSame((int) $merged->getKey(), (int) $order->fresh()->customer_profile_id);
        $this->assertSame((int) $merged->getKey(), (int) $note->fresh()->customer_profile_id);
        $this->assertSame((int) $merged->getKey(), (int) $task->fresh()->customer_profile_id);
        $this->assertSame((int) $merged->getKey(), (int) $touchpoint->fresh()->customer_profile_id);
        $this->assertSame('9999-1111', $merged->phone);
        $this->assertTrue((bool) $merged->whatsapp_opt_in);
        $this->assertStringContainsString('Cliente contactada por WhatsApp.', (string) $merged->crm_notes);
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
