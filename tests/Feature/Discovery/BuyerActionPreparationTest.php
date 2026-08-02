<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\BuyerActionPreparationService;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Domain\Discovery\BuyerActionRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class BuyerActionPreparationTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_it_prepares_confirmation_only_order_and_reservation_payloads_without_executing_them(): void
    {
        $productTenant = Tenant::factory()->create([
            'name' => 'Cafe Centro',
            'slug' => 'cafe-centro',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assignHonduras($productTenant);
        $product = Product::factory()->forTenant($productTenant)->create([
            'name' => 'Bolsa de café',
            'price' => 75.00,
            'price_minor' => 7500,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'allow_backorders' => false,
        ]);

        $serviceTenant = Tenant::factory()->create([
            'name' => 'Studio North',
            'slug' => 'studio-north',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $serviceLocation = $this->assignCanada($serviceTenant);
        $service = Service::factory()->create([
            'tenant_id' => $serviceTenant->id,
            'name' => 'Portrait Session',
            'description' => 'Reserve a published portrait service.',
            'price' => 120.00,
            'price_minor' => 12000,
            'currency' => 'CAD',
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($serviceTenant, $service->id, $serviceLocation);
        $this->createAvailabilityRule(
            tenant: $serviceTenant,
            locationId: $serviceLocation,
            serviceId: $service->id,
            timezoneName: 'America/Toronto',
            localDate: '2026-08-05',
        );

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $productProjectionId = (string) DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->value('id');
        $serviceProjectionId = (string) DB::table('discovery_published_offers')
            ->where('projection_key', 'service:'.$service->id)
            ->value('id');

        $preparedOrder = app(BuyerActionPreparationService::class)->prepare(new BuyerActionRequest(
            projectionId: $productProjectionId,
            actionType: 'order_preparation',
            quantity: 2,
            desiredLocalDate: null,
        ));

        self::assertSame('requires_confirmation', $preparedOrder->status);
        self::assertTrue($preparedOrder->requiresConfirmation);
        self::assertFalse($preparedOrder->executionAllowed);
        self::assertSame(15000, $preparedOrder->payload['quoted_total_minor']);
        self::assertSame('HNL', $preparedOrder->payload['currency_code']);

        $preparedReservation = app(BuyerActionPreparationService::class)->prepare(new BuyerActionRequest(
            projectionId: $serviceProjectionId,
            actionType: 'reservation_preparation',
            quantity: 1,
            desiredLocalDate: '2026-08-05',
        ));

        self::assertSame('requires_confirmation', $preparedReservation->status);
        self::assertTrue($preparedReservation->requiresConfirmation);
        self::assertFalse($preparedReservation->executionAllowed);
        self::assertSame('2026-08-05', $preparedReservation->payload['desired_local_date']);
        self::assertSame('America/Toronto', $preparedReservation->payload['timezone_name']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('appointments', 0);
        self::assertSame(2, DB::table('discovery_buyer_action_preparations')->count());
    }

    public function test_it_rejects_unavailable_and_prohibited_offers_before_any_economic_action(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Blocked Seller',
            'slug' => 'blocked-seller',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assignHonduras($tenant);

        $unavailable = Product::factory()->forTenant($tenant)->create([
            'name' => 'Sold Out Beans',
            'price' => 50.00,
            'price_minor' => 5000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'allow_backorders' => false,
        ]);

        $prohibited = Product::factory()->forTenant($tenant)->create([
            'name' => 'Prohibited launch bundle',
            'price' => 60.00,
            'price_minor' => 6000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
        ]);

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $unavailableProjectionId = (string) DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$unavailable->id)
            ->value('id');
        $prohibitedProjectionId = (string) DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$prohibited->id)
            ->value('id');

        $unavailableAction = app(BuyerActionPreparationService::class)->prepare(new BuyerActionRequest(
            projectionId: $unavailableProjectionId,
            actionType: 'order_preparation',
            quantity: 1,
            desiredLocalDate: null,
        ));
        self::assertSame('rejected', $unavailableAction->status);
        self::assertSame('unavailable', $unavailableAction->reasonCode);

        $prohibitedAction = app(BuyerActionPreparationService::class)->prepare(new BuyerActionRequest(
            projectionId: $prohibitedProjectionId,
            actionType: 'order_preparation',
            quantity: 1,
            desiredLocalDate: null,
        ));
        self::assertSame('rejected', $prohibitedAction->status);
        self::assertSame('prohibited', $prohibitedAction->reasonCode);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('appointments', 0);
    }
}
