<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WaveTwoConsolidationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wave_two_alignment_keeps_snapshots_and_canonical_indexes(): void
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create();

        $menuSectionId = (int) DB::table('menu_sections')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Temporada',
            'sort_order' => 77,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $holdId = (string) Str::uuid();

        DB::table('inventory_holds')->insert([
            'id' => $holdId,
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'session_id' => 'wave2-test-session',
            'location_id' => null,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var object{up: callable():void} $migration */
        $migration = require base_path('database/migrations/2026_04_15_090000_consolidate_menu_sections_and_inventory_holds.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('menu_sections_legacy_snapshot'));
        $this->assertTrue(Schema::hasTable('inventory_holds_legacy_snapshot'));

        $this->assertNotNull(
            DB::table('menu_sections_legacy_snapshot')
                ->where('menu_section_id', $menuSectionId)
                ->first()
        );

        $this->assertNotNull(
            DB::table('inventory_holds_legacy_snapshot')
                ->where('hold_id', $holdId)
                ->first()
        );

        $menuSectionIndexes = collect(Schema::getIndexes('menu_sections'));
        $inventoryHoldIndexes = collect(Schema::getIndexes('inventory_holds'));

        $this->assertTrue(
            $menuSectionIndexes->contains(
                static fn (array $index): bool => ($index['name'] ?? null) === 'menu_sections_tenant_id_sort_order_index'
            )
        );

        $this->assertTrue(
            $inventoryHoldIndexes->contains(
                static fn (array $index): bool => ($index['name'] ?? null) === 'inv_holds_unique'
            )
        );

        $this->assertNotNull(
            DB::table('inventory_holds')
                ->where('id', $holdId)
                ->whereNotNull('location_id')
                ->value('location_id')
        );
    }
}
