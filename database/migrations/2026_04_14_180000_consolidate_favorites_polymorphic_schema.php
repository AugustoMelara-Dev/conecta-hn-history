<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('favorites')) {
            return;
        }

        if (! Schema::hasTable('favorites_legacy_backup')) {
            Schema::create('favorites_legacy_backup', function (Blueprint $table): void {
                $table->unsignedBigInteger('favorite_id')->primary();
                $table->uuid('user_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        $hasProductId = Schema::hasColumn('favorites', 'product_id');
        $hasFavoritableId = Schema::hasColumn('favorites', 'favoritable_id');
        $hasFavoritableType = Schema::hasColumn('favorites', 'favoritable_type');

        if (! $hasFavoritableId || ! $hasFavoritableType) {
            Schema::table('favorites', function (Blueprint $table) use ($hasFavoritableId, $hasFavoritableType): void {
                if (! $hasFavoritableId) {
                    $table->string('favoritable_id', 36)->nullable()->after('user_id');
                }

                if (! $hasFavoritableType) {
                    $table->string('favoritable_type', 191)->nullable()->after('favoritable_id');
                }
            });
        }

        $favoritableMeta = collect(Schema::getColumns('favorites'))
            ->firstWhere('name', 'favoritable_id');
        $favoritableType = strtolower((string) ($favoritableMeta['type'] ?? $favoritableMeta['type_name'] ?? ''));

        if (str_contains($favoritableType, 'bigint') || str_contains($favoritableType, 'int')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->string('favoritable_id', 64)->nullable()->change();
            });
        }

        if ($hasProductId) {
            DB::table('favorites_legacy_backup')->insertUsing(
                ['favorite_id', 'user_id', 'product_id', 'captured_at'],
                DB::table('favorites')
                    ->selectRaw('id, user_id, product_id, NOW()')
                    ->whereNotNull('product_id')
                    ->whereNotIn('id', DB::table('favorites_legacy_backup')->select('favorite_id'))
            );

            if (Schema::hasTable('products') && Schema::hasColumn('products', 'tenant_id')) {
                $driver = DB::getDriverName();

                if ($driver === 'mysql') {
                    DB::statement(
                        "UPDATE favorites f
                        INNER JOIN products p ON p.id = f.product_id
                        SET f.favoritable_id = p.tenant_id,
                            f.favoritable_type = '".addslashes(Tenant::class)."'
                        WHERE f.product_id IS NOT NULL
                          AND (f.favoritable_id IS NULL OR f.favoritable_type IS NULL)"
                    );
                } else {
                    DB::table('favorites')
                        ->whereNotNull('product_id')
                        ->where(function ($query): void {
                            $query->whereNull('favoritable_id')
                                ->orWhereNull('favoritable_type');
                        })
                        ->orderBy('id')
                        ->chunkById(500, function ($rows): void {
                            foreach ($rows as $row) {
                                $tenantId = DB::table('products')->where('id', $row->product_id)->value('tenant_id');

                                if ($tenantId === null) {
                                    continue;
                                }

                                DB::table('favorites')
                                    ->where('id', $row->id)
                                    ->update([
                                        'favoritable_id' => (string) $tenantId,
                                        'favoritable_type' => Tenant::class,
                                    ]);
                            }
                        });
                }
            } else {
                DB::table('favorites')
                    ->whereNotNull('product_id')
                    ->where(function ($query): void {
                        $query->whereNull('favoritable_id')
                            ->orWhereNull('favoritable_type');
                    })
                    ->update([
                        'favoritable_id' => DB::raw('CAST(product_id AS CHAR)'),
                        'favoritable_type' => Product::class,
                    ]);
            }
        }

        DB::table('favorites')
            ->whereNotNull('favoritable_id')
            ->whereNull('favoritable_type')
            ->update(['favoritable_type' => Tenant::class]);

        $this->backfillNullFavoritableFields();

        Schema::table('favorites', function (Blueprint $table): void {
            $table->string('favoritable_id', 64)->nullable(false)->change();
            $table->string('favoritable_type', 191)->nullable(false)->change();
        });

        if (! $this->hasIndex('favorites', 'favorites_unique')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->unique(['user_id', 'favoritable_id', 'favoritable_type'], 'favorites_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('favorites') || ! Schema::hasTable('favorites_legacy_backup')) {
            return;
        }

        Schema::table('favorites', function (Blueprint $table): void {
            $table->string('favoritable_id', 64)->nullable()->change();
            $table->string('favoritable_type', 191)->nullable()->change();
        });

        if (! Schema::hasColumn('favorites', 'product_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'UPDATE favorites f
                 INNER JOIN favorites_legacy_backup b ON b.favorite_id = f.id
                 SET f.product_id = b.product_id,
                     f.favoritable_id = NULL,
                     f.favoritable_type = NULL'
            );

            return;
        }

        DB::table('favorites_legacy_backup')
            ->orderBy('favorite_id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('favorites')
                        ->where('id', $row->favorite_id)
                        ->update([
                            'product_id' => $row->product_id,
                            'favoritable_id' => null,
                            'favoritable_type' => null,
                        ]);
                }
            }, 'favorite_id');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = collect(Schema::getIndexes($table));

        return $indexes->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $indexName);
    }

    private function backfillNullFavoritableFields(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "UPDATE favorites
                 SET favoritable_id = CAST(id AS CHAR),
                     favoritable_type = '".addslashes(Product::class)."'
                 WHERE favoritable_id IS NULL OR favoritable_type IS NULL"
            );

            return;
        }

        DB::table('favorites')
            ->where(function ($query): void {
                $query->whereNull('favoritable_id')
                    ->orWhereNull('favoritable_type');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('favorites')
                        ->where('id', $row->id)
                        ->update([
                            'favoritable_id' => (string) $row->id,
                            'favoritable_type' => Product::class,
                        ]);
                }
            });
    }
};
