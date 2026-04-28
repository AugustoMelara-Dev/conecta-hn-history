<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Carbon\Carbon;
use Database\Seeders\E2EStorefrontSeeder;
use Database\Seeders\GeoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookingSlotsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function e2e_barbershop_fixture_exposes_available_slots(): void
    {
        $this->seed(GeoSeeder::class);
        $this->seed(E2EStorefrontSeeder::class);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()
            ->where('slug', 'barberia-norte-pro')
            ->firstOrFail();

        app(TenantContext::class)->setTenant($tenant->id);

        /** @var Service $service */
        $service = Service::query()
            ->where('name', 'Corte premium con barba')
            ->firstOrFail();

        /** @var ServiceProvider $provider */
        $provider = ServiceProvider::query()
            ->where('name', 'Marco Diaz')
            ->firstOrFail();

        app(TenantContext::class)->reset();

        $date = now()->addDay()->format('Y-m-d');

        $response = $this->getJson("/t/{$tenant->slug}/book/slots?date={$date}&service_id={$service->id}&provider_id={$provider->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'slots' => [
                    '*' => ['time', 'display'],
                ],
                'timeline' => [
                    '*' => ['time', 'display', 'available'],
                ],
            ]);

        self::assertNotEmpty($response->json('slots'));
    }

    #[Test]
    public function timeline_marks_occupied_slot_when_provider_already_has_a_booking(): void
    {
        $this->seed(GeoSeeder::class);
        $this->seed(E2EStorefrontSeeder::class);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()
            ->where('slug', 'barberia-norte-pro')
            ->firstOrFail();

        app(TenantContext::class)->setTenant($tenant->id);

        /** @var Service $service */
        $service = Service::query()
            ->where('name', 'Corte premium con barba')
            ->firstOrFail();

        /** @var ServiceProvider $provider */
        $provider = ServiceProvider::query()
            ->where('name', 'Marco Diaz')
            ->firstOrFail();

        app(TenantContext::class)->reset();

        $date = now()->addDay()->format('Y-m-d');

        $initial = $this->getJson("/t/{$tenant->slug}/book/slots?date={$date}&service_id={$service->id}&provider_id={$provider->id}")
            ->assertOk();

        $firstAvailable = collect($initial->json('slots'))->first();
        self::assertNotNull($firstAvailable, 'Fixture should expose at least one available slot.');

        $scheduledAt = Carbon::parse($date.' '.$firstAvailable['time']);

        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'scheduled_at' => $scheduledAt,
            'end_at' => $scheduledAt->copy()->addMinutes((int) $service->duration_minutes),
            'status' => Appointment::STATUS_PENDING,
            'customer_name' => 'Cliente Bloqueado',
            'customer_email' => 'bloqueado@example.test',
            'customer_phone' => '9999-0001',
        ]);

        $after = $this->getJson("/t/{$tenant->slug}/book/slots?date={$date}&service_id={$service->id}&provider_id={$provider->id}")
            ->assertOk();

        $timeline = collect($after->json('timeline'));
        $available = collect($after->json('slots'));

        self::assertTrue(
            $timeline->contains(fn (array $slot): bool => $slot['time'] === $firstAvailable['time'] && $slot['available'] === false)
        );
        self::assertFalse(
            $available->contains(fn (array $slot): bool => $slot['time'] === $firstAvailable['time'])
        );
    }

    #[Test]
    public function hidden_tenant_cannot_expose_booking_slots(): void
    {
        $tenant = Tenant::factory()->pro()->create([
            'slug' => 'hidden-booking-slots',
            'business_type' => 'service',
            'publication_paused_at' => now(),
        ]);

        app(TenantContext::class)->setTenant($tenant->id);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $provider = ServiceProvider::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        app(TenantContext::class)->reset();

        $date = now()->addDay()->format('Y-m-d');

        $this->getJson("/t/{$tenant->slug}/book/slots?date={$date}&service_id={$service->id}&provider_id={$provider->id}")
            ->assertNotFound();
    }
}
