<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add branding fields for Tenant profile customization
 *
 * Adds:
 * - cover_path: Hero/banner image for storefront
 * - about: Extended description / "Our Story"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'cover_path')) {
                $table->string('cover_path', 2048)->nullable()->after('slug');
            }

            if (! Schema::hasColumn('tenants', 'about')) {
                $table->text('about')->nullable()->after('cover_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'about']);
        });
    }
};
