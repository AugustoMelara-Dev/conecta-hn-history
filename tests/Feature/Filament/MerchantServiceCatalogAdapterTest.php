<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\ServiceCatalog\MerchantServiceCatalogAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class MerchantServiceCatalogAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_create_uses_trusted_authority_market_minor_units_locations_and_attempt_replay(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        $adapter = app(MerchantServiceCatalogAdapter::class);
        $payload = [
            'tenant_id' => Tenant::factory()->create()->id,
            'currency' => 'HNL',
            'name' => 'Strategy session',
            'description' => 'Focused advice.',
            'price' => '123.45',
            'duration_minutes' => '45',
            'location_ids' => [(string) $location->id],
            'is_active' => true,
        ];

        $first = $adapter->createService($tenant, $owner, $payload, 'filament-service-create-1');
        $replay = $adapter->createService($tenant, $owner, $payload, 'filament-service-create-1');

        self::assertSame($first->getKey(), $replay->getKey());
        self::assertSame((string) $tenant->id, (string) $first->tenant_id);
        self::assertSame('CAD', $first->currency);
        self::assertSame(12_345, $first->price_minor);
        self::assertSame('123.45', (string) $first->getRawOriginal('price'));
        self::assertSame(1, DB::table('services')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('service_locations', [
            'tenant_id' => $tenant->id,
            'service_id' => $first->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_service_edit_and_archive_are_tenant_scoped(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        [$other, $otherOwner] = $this->serviceTenant();
        GlobalMarketFixture::assignCanada($other);
        $adapter = app(MerchantServiceCatalogAdapter::class);
        $service = $adapter->createService($tenant, $owner, [
            'name' => 'Original',
            'description' => 'Remove me',
            'price' => '10.00',
            'duration_minutes' => 30,
            'location_ids' => [(string) $location->id],
        ], 'service-edit-source');

        $updated = $adapter->updateService($tenant, $owner, $service, [
            'name' => 'Updated',
            'price' => '10.01',
            'duration_minutes' => 60,
            'description' => null,
            'is_active' => true,
        ]);
        self::assertSame('Updated', $updated->name);
        self::assertSame(1_001, $updated->price_minor);
        self::assertNull($updated->description);

        try {
            $adapter->updateService($other, $otherOwner, $service, ['name' => 'Hostile']);
            self::fail('Cross-tenant service edits must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertSame('Updated', $service->fresh()->name);
        }

        $adapter->archiveService($tenant, $owner, $service);
        self::assertFalse((bool) $service->fresh()->is_active);
    }

    public function test_provider_create_edit_and_archive_use_the_same_canonical_boundary(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        $adapter = app(MerchantServiceCatalogAdapter::class);

        $provider = $adapter->createProvider($tenant, $owner, [
            'tenant_id' => Tenant::factory()->create()->id,
            'name' => 'Alex',
            'email' => 'ALEX@EXAMPLE.TEST',
            'location_ids' => [(string) $location->id],
            'is_active' => true,
        ], 'provider-create-1');
        $updated = $adapter->updateProvider($tenant, $owner, $provider, [
            'name' => 'Alex Morgan',
            'phone' => '+1 416 555 0199',
        ]);

        self::assertSame('Alex Morgan', $updated->name);
        self::assertSame('alex@example.test', $updated->email);
        $adapter->archiveProvider($tenant, $owner, $provider);
        self::assertFalse((bool) $provider->fresh()->is_active);
    }

    public function test_decimal_price_rejects_binary_float_input(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        GlobalMarketFixture::assignCanada($tenant);

        $this->expectException(ValidationException::class);
        app(MerchantServiceCatalogAdapter::class)->createService($tenant, $owner, [
            'name' => 'Unsafe price',
            'price' => 10.01,
            'duration_minutes' => 30,
        ], 'unsafe-float');
    }

    /** @return array{Tenant, User} */
    private function serviceTenant(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);

        return [$tenant, $owner];
    }
}
