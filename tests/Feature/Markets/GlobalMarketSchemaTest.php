<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class GlobalMarketSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_reference_and_market_pack_tables_exist(): void
    {
        $this->assertTableColumns('countries', [
            'code', 'name', 'native_name', 'calling_code', 'is_active',
        ]);
        $this->assertTableColumns('currencies', [
            'code', 'name', 'symbol', 'minor_unit', 'is_active',
        ]);
        $this->assertTableColumns('locales', [
            'code', 'name', 'native_name', 'is_active',
        ]);
        $this->assertTableColumns('timezones', [
            'name', 'label', 'utc_offset', 'is_active',
        ]);
        $this->assertTableColumns('market_packs', [
            'id', 'key', 'country_code', 'name', 'is_active',
        ]);
        $this->assertTableColumns('market_pack_versions', [
            'id', 'market_pack_id', 'version', 'status', 'default_locale_code',
            'default_currency_code', 'default_timezone_name', 'supported_locales',
            'supported_currencies', 'supported_timezones', 'capabilities',
            'address_schema', 'effective_from', 'published_at',
        ]);
    }

    public function test_global_geography_preserves_legacy_identifier_bridges(): void
    {
        $this->assertTableColumns('administrative_areas', [
            'id', 'country_code', 'parent_id', 'level', 'type', 'code',
            'name', 'slug', 'legacy_department_id', 'legacy_municipality_id',
        ]);
        $this->assertTableColumns('localities', [
            'id', 'country_code', 'administrative_area_id', 'name', 'slug',
            'timezone_name', 'latitude', 'longitude', 'legacy_city_id', 'is_active',
        ]);
    }

    public function test_tenant_market_and_location_contracts_are_explicit(): void
    {
        $this->assertTableColumns('tenant_markets', [
            'id', 'tenant_id', 'market_pack_version_id', 'country_code',
            'locale_code', 'currency_code', 'timezone_name', 'is_primary',
        ]);
        $this->assertTableColumns('locations', [
            'id', 'tenant_id', 'country_code', 'administrative_area_id',
            'locality_id', 'locale_code', 'currency_code', 'timezone_name',
            'address', 'is_primary',
        ]);
    }

    public function test_rollback_removes_only_the_expanded_market_kernel_and_preserves_legacy_geography(): void
    {
        $migration = require database_path('migrations/2026_07_28_000001_create_global_market_kernel.php');

        $migration->down();

        self::assertTrue(Schema::hasTable('tenants'));
        self::assertTrue(Schema::hasTable('departments'));
        self::assertTrue(Schema::hasTable('municipalities'));
        self::assertTrue(Schema::hasTable('cities'));
        self::assertTrue(Schema::hasColumn('tenants', 'city_id'));
        self::assertFalse(Schema::hasTable('countries'));
        self::assertFalse(Schema::hasTable('tenant_markets'));
        self::assertFalse(Schema::hasTable('locations'));

        // Restore the expanded schema for framework teardown.
        $migration->up();
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertTableColumns(string $table, array $columns): void
    {
        self::assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn($table, $column),
                "Expected [{$table}.{$column}] to exist.",
            );
        }
    }
}
