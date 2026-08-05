<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_slug_unique');
            $table->unique(['tenant_id', 'slug'], 'products_tenant_slug_unique');
        });
    }

    public function down(): void
    {
        // This cutover becomes operationally irreversible after normal tenant
        // scoped duplicates exist. Follow docs/runbooks/product-slug-tenant-cutover.md
        // and recover forward; never mutate public slugs just to force rollback.
        $hasCrossTenantDuplicates = DB::table('products')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasCrossTenantDuplicates) {
            throw new RuntimeException(
                'Cannot restore global product slug uniqueness while cross-tenant duplicate slugs exist.'
            );
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_tenant_slug_unique');
            $table->unique('slug', 'products_slug_unique');
        });
    }
};
