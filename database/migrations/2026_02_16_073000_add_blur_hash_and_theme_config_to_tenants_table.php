<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC1 Fix: Add blur_hash and theme_config columns to tenants table.
 *
 * CONTEXT:
 * - blur_hash: Referenced in EloquentDirectoryReader SELECT projections
 *   for card rendering (blur-up placeholder effect). Never had a migration.
 * - theme_config: Cast as 'array' in Tenant model and used by
 *   getThemeConfigAttribute() accessor. Never had a migration.
 *
 * Both columns are nullable with sensible defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'blur_hash')) {
                $table->string('blur_hash')->nullable()->after('cover_path')
                    ->comment('Blurhash placeholder for image lazy loading');
            }

            if (! Schema::hasColumn('tenants', 'theme_config')) {
                $table->json('theme_config')->nullable()->after('brand_color')
                    ->comment('JSON theme configuration for micrositio customization');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('tenants', 'blur_hash')) {
                $columns[] = 'blur_hash';
            }
            if (Schema::hasColumn('tenants', 'theme_config')) {
                $columns[] = 'theme_config';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
