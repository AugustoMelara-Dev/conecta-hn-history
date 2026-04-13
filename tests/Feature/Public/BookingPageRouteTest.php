<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookingPageRouteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_booking_wizard_with_a_preselected_service_when_requested_from_the_microsite(): void
    {
        $user = User::factory()->create();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'bufete-lex-hn',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        /** @var Service $service */
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta legal inicial',
            'is_active' => true,
        ]);

        ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Lopez',
            'is_active' => true,
        ]);

        app(TenantContext::class)->reset();

        $response = $this->get("/t/{$tenant->slug}/book?service={$service->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Public/Book/Index')
            ->where('tenant.slug', $tenant->slug)
            ->where('preselectedServiceId', (string) $service->id)
            ->has('services', 1)
            ->has('providers', 1)
        );
    }

    #[Test]
    public function it_accepts_numeric_ids_from_the_public_booking_wizard_and_redirects_to_success(): void
    {
        $user = User::factory()->create();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'barberia-norte-pro',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        /** @var Service $service */
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corte premium con barba',
            'duration_minutes' => 50,
            'price' => 320,
            'is_active' => true,
        ]);

        /** @var ServiceProvider $provider */
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Marco Diaz',
            'is_active' => true,
        ]);

        app(TenantContext::class)->reset();

        $date = now()->addDay()->format('Y-m-d');

        $response = $this->from("/t/{$tenant->slug}/book")
            ->post("/t/{$tenant->slug}/book", [
                'service_id' => $service->id,
                'service_provider_id' => $provider->id,
                'date' => $date,
                'time' => '09:00',
                'customer_name' => 'Cliente E2E',
                'customer_email' => 'cliente.e2e@example.test',
                'customer_phone' => '9999-1111',
                'customer_notes' => 'Reserva creada desde feature test.',
                'contact_consent' => true,
            ]);

        $response->assertRedirectContains("/t/{$tenant->slug}/book/success?appointment=");

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'customer_email' => 'cliente.e2e@example.test',
            'status' => Appointment::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function it_renders_the_success_page_after_creating_a_public_booking(): void
    {
        config(['app.e2e_browser_release' => true]);

        $user = User::factory()->create();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'barberia-norte-pro',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        /** @var Service $service */
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corte premium con barba',
            'duration_minutes' => 50,
            'price' => 320,
            'is_active' => true,
        ]);

        /** @var ServiceProvider $provider */
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Marco Diaz',
            'is_active' => true,
        ]);

        app(TenantContext::class)->reset();

        $date = now()->addDay()->format('Y-m-d');

        $storeResponse = $this->from("/t/{$tenant->slug}/book")
            ->post("/t/{$tenant->slug}/book", [
                'service_id' => $service->id,
                'service_provider_id' => $provider->id,
                'date' => $date,
                'time' => '09:00',
                'customer_name' => 'Cliente E2E',
                'customer_email' => 'cliente.e2e@example.test',
                'customer_phone' => '9999-1111',
                'customer_notes' => 'Reserva creada desde feature test.',
                'contact_consent' => true,
            ]);

        $storeResponse->assertRedirectContains("/t/{$tenant->slug}/book/success?appointment=");

        app(TenantContext::class)->reset();

        $successResponse = $this->get($storeResponse->headers->get('Location'));

        $successResponse->assertOk();
        $successResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Public/Book/Success')
            ->where('tenant.slug', $tenant->slug)
            ->where('appointment.service_name', 'Corte premium con barba')
            ->where('appointment.provider_name', 'Marco Diaz')
            ->where('appointment.customer_name', 'Cliente E2E')
        );
    }

    #[Test]
    public function it_rejects_cross_tenant_service_and_provider_ids_in_public_booking(): void
    {
        $user = User::factory()->create();
        /** @var Tenant $tenantA */
        $tenantA = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'barberia-tenant-a',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);
        /** @var Tenant $tenantB */
        $tenantB = Tenant::factory()->pro()->create([
            'slug' => 'barberia-tenant-b',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        /** @var Service $foreignService */
        $foreignService = Service::factory()->create([
            'tenant_id' => $tenantB->id,
            'is_active' => true,
        ]);
        /** @var ServiceProvider $foreignProvider */
        $foreignProvider = ServiceProvider::factory()->create([
            'tenant_id' => $tenantB->id,
            'is_active' => true,
        ]);

        app(TenantContext::class)->reset();

        $response = $this->post("/t/{$tenantA->slug}/book", [
            'service_id' => $foreignService->id,
            'service_provider_id' => $foreignProvider->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '09:00',
            'customer_name' => 'Intruso',
            'customer_email' => 'intruso@example.test',
            'customer_phone' => '9999-0000',
            'contact_consent' => true,
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('appointments', [
            'tenant_id' => $tenantA->id,
            'customer_email' => 'intruso@example.test',
        ]);
    }

    #[Test]
    public function it_blocks_excessive_same_day_bookings_for_the_same_contact(): void
    {
        config(['conecta.booking.max_pending_per_contact_per_day' => 1]);

        $user = User::factory()->create();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'dentista-guard',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        /** @var Service $service */
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Limpieza dental',
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        /** @var ServiceProvider $provider */
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dra. Alicia',
            'is_active' => true,
        ]);

        $scheduledAt = now()->addDay()->setTime(9, 0);

        app(TenantContext::class)->setTenant($tenant->id);

        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'scheduled_at' => $scheduledAt,
            'end_at' => $scheduledAt->copy()->addMinutes(45),
            'status' => Appointment::STATUS_PENDING,
            'customer_name' => 'Cliente Repetido',
            'customer_email' => 'cliente.repetido@example.test',
            'customer_phone' => '9999-8888',
        ]);

        app(TenantContext::class)->reset();

        $response = $this->from("/t/{$tenant->slug}/book")
            ->post("/t/{$tenant->slug}/book", [
                'service_id' => $service->id,
                'service_provider_id' => $provider->id,
                'date' => $scheduledAt->format('Y-m-d'),
                'time' => '10:00',
                'customer_name' => 'Cliente Repetido',
                'customer_email' => 'cliente.repetido@example.test',
                'customer_phone' => '9999-8888',
                'contact_consent' => true,
            ]);

        $response
            ->assertRedirect("/t/{$tenant->slug}/book")
            ->assertSessionHasErrors('customer_email');
    }
}
