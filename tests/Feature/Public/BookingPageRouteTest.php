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
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'bufete-lex-hn',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

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
        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'barberia-norte-pro',
            'business_type' => 'service',
            'status' => 'approved',
            'subscription_status' => 'active',
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corte premium con barba',
            'duration_minutes' => 50,
            'price' => 320,
            'is_active' => true,
        ]);

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
}
