<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BackfillOrderLocationsCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_backfills_legacy_orders_with_the_primary_active_location(): void
    {
        $tenantWithPrimary = Tenant::factory()->create();
        $tenantWithoutPrimary = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantWithPrimary->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantWithoutPrimary->id]);

        $primaryLocation = Location::query()->create([
            'tenant_id' => $tenantWithPrimary->id,
            'name' => 'Sucursal Centro',
            'slug' => 'sucursal-centro',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $secondaryLocation = Location::query()->create([
            'tenant_id' => $tenantWithPrimary->id,
            'name' => 'Sucursal Norte',
            'slug' => 'sucursal-norte',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $legacyOrder = Order::factory()->forTenant($tenantWithPrimary)->create([
            'user_id' => $userA->id,
            'location_id' => null,
        ]);

        $alreadyAssignedOrder = Order::factory()->forTenant($tenantWithPrimary)->create([
            'user_id' => $userA->id,
            'location_id' => $secondaryLocation->id,
        ]);

        $orphanedOrder = Order::factory()->forTenant($tenantWithoutPrimary)->create([
            'user_id' => $userB->id,
            'location_id' => null,
        ]);

        $this->artisan('vito:backfill-order-locations', ['--chunk' => 1])
            ->expectsOutput('Backfill complete. Updated: 1. Skipped: 1. Remaining without location: 1.')
            ->assertExitCode(0);

        self::assertSame((string) $primaryLocation->id, (string) $legacyOrder->fresh()?->location_id);
        self::assertSame((string) $secondaryLocation->id, (string) $alreadyAssignedOrder->fresh()?->location_id);
        self::assertNull($orphanedOrder->fresh()?->location_id);

        $this->artisan('vito:backfill-order-locations')
            ->expectsOutput('Backfill complete. Updated: 0. Skipped: 1. Remaining without location: 1.')
            ->assertExitCode(0);
    }
}
