<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignMenuSections();
        $this->alignInventoryHolds();
    }

    public function down(): void
    {
        $this->rollbackMenuSections();
        $this->rollbackInventoryHolds();
    }

    private function alignMenuSections(): void
    {
        if (! Schema::hasTable('menu_sections')) {
            return;
        }

        if (! Schema::hasTable('menu_sections_legacy_snapshot')) {
            Schema::create('menu_sections_legacy_snapshot', function (Blueprint $table): void {
                $table->unsignedBigInteger('menu_section_id')->primary();
                $table->integer('old_sort_order')->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        DB::table('menu_sections_legacy_snapshot')->insertUsing(
            ['menu_section_id', 'old_sort_order', 'captured_at'],
            DB::table('menu_sections')
                ->selectRaw('id, sort_order, CURRENT_TIMESTAMP')
                ->whereNotIn('id', DB::table('menu_sections_legacy_snapshot')->select('menu_section_id'))
        );

        DB::table('menu_sections')
            ->whereNull('is_active')
            ->update(['is_active' => true]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE menu_sections ms
                INNER JOIN (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY tenant_id
                        ORDER BY COALESCE(sort_order, 0), id
                    ) - 1 AS normalized_sort_order
                    FROM menu_sections
                ) ranked ON ranked.id = ms.id
                SET ms.sort_order = ranked.normalized_sort_order
            ');
        } else {
            $tenantIds = DB::table('menu_sections')
                ->select('tenant_id')
                ->distinct()
                ->pluck('tenant_id');

            foreach ($tenantIds as $tenantId) {
                $rows = DB::table('menu_sections')
                    ->where('tenant_id', $tenantId)
                    ->orderByRaw('COALESCE(sort_order, 0)')
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($rows as $index => $row) {
                    DB::table('menu_sections')
                        ->where('id', $row->id)
                        ->update(['sort_order' => $index]);
                }
            }
        }

        if (! $this->hasIndex('menu_sections', 'menu_sections_tenant_id_sort_order_index')) {
            Schema::table('menu_sections', function (Blueprint $table): void {
                $table->index(['tenant_id', 'sort_order']);
            });
        }
    }

    private function alignInventoryHolds(): void
    {
        if (! Schema::hasTable('inventory_holds')) {
            return;
        }

        if (! Schema::hasTable('inventory_holds_legacy_snapshot')) {
            Schema::create('inventory_holds_legacy_snapshot', function (Blueprint $table): void {
                $table->string('hold_id', 64)->primary();
                $table->unsignedBigInteger('legacy_numeric_id')->nullable();
                $table->uuid('legacy_location_id')->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        $this->ensureInventoryHoldUuidPrimaryKey();
        $this->ensureInventoryHoldLocationColumn();

        $legacyNumericIdSelect = Schema::hasColumn('inventory_holds', 'legacy_numeric_id')
            ? 'legacy_numeric_id'
            : 'NULL';
        $legacyLocationIdSelect = Schema::hasColumn('inventory_holds', 'location_id')
            ? 'location_id'
            : 'NULL';

        DB::table('inventory_holds_legacy_snapshot')->insertUsing(
            ['hold_id', 'legacy_numeric_id', 'legacy_location_id', 'captured_at'],
            DB::table('inventory_holds')
                ->selectRaw(
                    "CAST(id AS CHAR), {$legacyNumericIdSelect}, {$legacyLocationIdSelect}, CURRENT_TIMESTAMP"
                )
                ->whereNotIn(
                    DB::raw('CAST(id AS CHAR)'),
                    DB::table('inventory_holds_legacy_snapshot')->select('hold_id')
                )
        );

        $this->ensurePrimaryLocationsForTenantsWithHolds();
        $this->backfillInventoryHoldLocations();
        $this->ensureInventoryHoldLocationForeignKey();
        $this->normalizeInventoryHoldQuantityType();
        $this->ensureInventoryHoldIndexes();
    }

    private function rollbackMenuSections(): void
    {
        if (! Schema::hasTable('menu_sections') || ! Schema::hasTable('menu_sections_legacy_snapshot')) {
            return;
        }

        DB::table('menu_sections_legacy_snapshot')
            ->orderBy('menu_section_id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('menu_sections')
                        ->where('id', $row->menu_section_id)
                        ->update(['sort_order' => $row->old_sort_order]);
                }
            }, 'menu_section_id');
    }

    private function rollbackInventoryHolds(): void
    {
        if (! Schema::hasTable('inventory_holds') || ! Schema::hasTable('inventory_holds_legacy_snapshot')) {
            return;
        }

        DB::table('inventory_holds_legacy_snapshot')
            ->orderBy('hold_id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('inventory_holds')
                        ->where('id', $row->hold_id)
                        ->update([
                            'location_id' => $row->legacy_location_id,
                        ]);
                }
            });

        if ($this->hasIndex('inventory_holds', 'inv_holds_unique')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->dropUnique('inv_holds_unique');
            });
        }

        if (! $this->hasIndex('inventory_holds', 'inventory_holds_tenant_id_session_id_product_id_unique')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'session_id', 'product_id']);
            });
        }
    }

    private function ensureInventoryHoldUuidPrimaryKey(): void
    {
        if (! Schema::hasColumn('inventory_holds', 'id')) {
            return;
        }

        $idColumn = collect(Schema::getColumns('inventory_holds'))
            ->firstWhere('name', 'id');
        $idType = strtolower((string) ($idColumn['type'] ?? $idColumn['type_name'] ?? ''));

        if (str_contains($idType, 'char') || str_contains($idType, 'uuid')) {
            return;
        }

        if (! Schema::hasColumn('inventory_holds', 'legacy_numeric_id')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->unsignedBigInteger('legacy_numeric_id')->nullable()->after('id');
            });
        }

        DB::table('inventory_holds')
            ->whereNull('legacy_numeric_id')
            ->update(['legacy_numeric_id' => DB::raw('id')]);

        if (! Schema::hasColumn('inventory_holds', 'uuid_id')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->uuid('uuid_id')->nullable()->after('id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE inventory_holds SET uuid_id = UUID() WHERE uuid_id IS NULL');
            DB::statement('ALTER TABLE inventory_holds MODIFY id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE inventory_holds DROP PRIMARY KEY');
            DB::statement('ALTER TABLE inventory_holds DROP COLUMN id');
            DB::statement('ALTER TABLE inventory_holds CHANGE uuid_id id CHAR(36) NOT NULL');
            DB::statement('ALTER TABLE inventory_holds ADD PRIMARY KEY (id)');
        } else {
            DB::table('inventory_holds')
                ->whereNull('uuid_id')
                ->orderBy('legacy_numeric_id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('inventory_holds')
                            ->where('legacy_numeric_id', $row->legacy_numeric_id)
                            ->update(['uuid_id' => (string) Str::uuid()]);
                    }
                }, 'legacy_numeric_id');
        }
    }

    private function ensureInventoryHoldLocationColumn(): void
    {
        if (Schema::hasColumn('inventory_holds', 'location_id')) {
            return;
        }

        Schema::table('inventory_holds', function (Blueprint $table): void {
            $table->uuid('location_id')->nullable()->after('session_id');
        });
    }

    private function ensurePrimaryLocationsForTenantsWithHolds(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        $tenantIds = DB::table('inventory_holds')
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $locationExists = DB::table('locations')
                ->where('tenant_id', $tenantId)
                ->exists();

            if ($locationExists) {
                $primaryExists = DB::table('locations')
                    ->where('tenant_id', $tenantId)
                    ->where('is_primary', true)
                    ->exists();

                if (! $primaryExists) {
                    DB::table('locations')
                        ->where('tenant_id', $tenantId)
                        ->orderBy('created_at')
                        ->limit(1)
                        ->update(['is_primary' => true]);
                }

                continue;
            }

            $slug = 'principal-'.substr((string) $tenantId, 0, 8);

            DB::table('locations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Sucursal principal',
                'slug' => $slug,
                'address' => null,
                'phone' => null,
                'contact_name' => null,
                'hours_data' => null,
                'is_active' => true,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillInventoryHoldLocations(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE inventory_holds ih
                INNER JOIN (
                    SELECT
                        tenant_id,
                        COALESCE(
                            MAX(CASE WHEN is_primary = 1 THEN id END),
                            MIN(id)
                        ) AS resolved_location_id
                    FROM locations
                    GROUP BY tenant_id
                ) loc ON loc.tenant_id = ih.tenant_id
                SET ih.location_id = loc.resolved_location_id
                WHERE ih.location_id IS NULL
            ');

            return;
        }

        DB::table('inventory_holds')
            ->whereNull('location_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $locationId = DB::table('locations')
                        ->where('tenant_id', $row->tenant_id)
                        ->orderByDesc('is_primary')
                        ->orderBy('created_at')
                        ->value('id');

                    if ($locationId === null) {
                        continue;
                    }

                    DB::table('inventory_holds')
                        ->where('id', $row->id)
                        ->update(['location_id' => $locationId]);
                }
            }, 'id');
    }

    private function ensureInventoryHoldLocationForeignKey(): void
    {
        if (! Schema::hasTable('locations') || ! Schema::hasColumn('inventory_holds', 'location_id')) {
            return;
        }

        if ($this->hasForeign('inventory_holds', 'inventory_holds_location_id_foreign')) {
            return;
        }

        Schema::table('inventory_holds', function (Blueprint $table): void {
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();
        });
    }

    private function normalizeInventoryHoldQuantityType(): void
    {
        if (! Schema::hasColumn('inventory_holds', 'quantity')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventory_holds MODIFY quantity DECIMAL(12,3) NOT NULL');
        }
    }

    private function ensureInventoryHoldIndexes(): void
    {
        $this->ensureInventoryHoldForeignKeyIndexes();

        if ($this->hasIndex('inventory_holds', 'inventory_holds_tenant_id_session_id_product_id_unique')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->dropUnique('inventory_holds_tenant_id_session_id_product_id_unique');
            });
        }

        if ($this->hasIndex('inventory_holds', 'inventory_holds_tenant_id_session_id_location_id_product_id_unique')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->dropUnique('inventory_holds_tenant_id_session_id_location_id_product_id_unique');
            });
        }

        if (! $this->hasIndex('inventory_holds', 'inv_holds_unique')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'session_id', 'location_id', 'product_id'], 'inv_holds_unique');
            });
        }

        if (! $this->hasIndex('inventory_holds', 'inventory_holds_expires_at_index')) {
            Schema::table('inventory_holds', function (Blueprint $table): void {
                $table->index('expires_at');
            });
        }
    }

    private function ensureInventoryHoldForeignKeyIndexes(): void
    {
        Schema::table('inventory_holds', function (Blueprint $table): void {
            if (! $this->hasIndexWithColumns('inventory_holds', ['tenant_id'])) {
                $table->index('tenant_id', 'inventory_holds_tenant_id_idx');
            }

            if (! $this->hasIndexWithColumns('inventory_holds', ['product_id'])) {
                $table->index('product_id', 'inventory_holds_product_id_idx');
            }

            if (Schema::hasColumn('inventory_holds', 'location_id') && ! $this->hasIndexWithColumns('inventory_holds', ['location_id'])) {
                $table->index('location_id', 'inventory_holds_location_id_idx');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $indexName);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexWithColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $normalizedColumns = array_values($columns);

        return collect(Schema::getIndexes($table))
            ->contains(static function (array $index) use ($normalizedColumns): bool {
                $indexColumns = array_values((array) ($index['columns'] ?? []));

                return $indexColumns === $normalizedColumns;
            });
    }

    private function hasForeign(string $table, string $foreignName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.table_constraints')
                ->whereRaw('constraint_schema = database()')
                ->where('table_name', $table)
                ->where('constraint_type', 'FOREIGN KEY')
                ->where('constraint_name', $foreignName)
                ->exists();
        }

        return collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreign): bool => (string) ($foreign['name'] ?? '') === $foreignName);
    }
};
