<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Database\Seeders\E2EStorefrontSeeder;
use Database\Seeders\GeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookingSlotsTest extends TestCase
{
    use RefreshDatabase;

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
            ]);

        self::assertNotEmpty($response->json('slots'));
    }
}
