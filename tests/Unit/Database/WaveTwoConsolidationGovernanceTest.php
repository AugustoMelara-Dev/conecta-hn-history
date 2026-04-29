<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

final class WaveTwoConsolidationGovernanceTest extends TestCase
{
    private const MIGRATION = __DIR__.'/../../../database/migrations/2026_04_15_090000_consolidate_menu_sections_and_inventory_holds.php';

    public function test_wave_two_alignment_keeps_snapshots_and_canonical_indexes(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString("Schema::create('menu_sections_legacy_snapshot'", $source);
        $this->assertStringContainsString("Schema::create('inventory_holds_legacy_snapshot'", $source);
        $this->assertStringContainsString("'menu_section_id'", $source);
        $this->assertStringContainsString("'hold_id'", $source);
        $this->assertStringContainsString("'old_sort_order'", $source);
        $this->assertStringContainsString("'legacy_numeric_id'", $source);
        $this->assertStringContainsString("'legacy_location_id'", $source);

        $this->assertStringContainsString("DB::table('menu_sections_legacy_snapshot')->insertUsing", $source);
        $this->assertStringContainsString("DB::table('inventory_holds_legacy_snapshot')->insertUsing", $source);
        $this->assertStringContainsString('CURRENT_TIMESTAMP', $source);

        $this->assertStringContainsString('menu_sections_tenant_id_sort_order_index', $source);
        $this->assertStringContainsString("['tenant_id', 'sort_order']", $source);
        $this->assertStringContainsString('inv_holds_unique', $source);
        $this->assertStringContainsString("['tenant_id', 'session_id', 'location_id', 'product_id']", $source);
        $this->assertStringContainsString('inventory_holds_expires_at_index', $source);
    }

    public function test_wave_two_alignment_backfills_inventory_hold_locations_without_legacy_unique_contract(): void
    {
        $source = $this->migrationSource();

        $this->assertStringContainsString('ensurePrimaryLocationsForTenantsWithHolds', $source);
        $this->assertStringContainsString('backfillInventoryHoldLocations', $source);
        $this->assertStringContainsString('ensureInventoryHoldLocationForeignKey', $source);
        $this->assertStringContainsString('ensureInventoryHoldIndexes', $source);
        $this->assertStringContainsString('UPDATE inventory_holds ih', $source);
        $this->assertStringContainsString('WHERE ih.location_id IS NULL', $source);
        $this->assertStringContainsString('inventory_holds_tenant_id_session_id_product_id_unique', $source);
        $this->assertStringContainsString('inventory_holds_tenant_id_session_id_location_id_product_id_unique', $source);
    }

    private function migrationSource(): string
    {
        $source = file_get_contents(self::MIGRATION);

        $this->assertIsString($source, 'Wave two consolidation migration must be readable.');

        return $source;
    }
}
