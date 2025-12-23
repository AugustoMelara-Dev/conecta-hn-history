<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand Customization Migration
 *
 * Adds visual customization attributes to tenants table:
 * - brand_color: NOT NULL with default for consistent CSS variable injection
 * - hero_image: NULLABLE (frontend has gradient fallback)
 * - slogan: NULLABLE short text for brand subtitle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════
            // BRAND COLOR: Primary color for CSS variable injection
            // NOT NULL with default to prevent broken styling on frontend
            // Format: Hex code (e.g., #2563EB)
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'brand_color')) {
                $table->string('brand_color', 9)
                    ->default('#2563EB')
                    ->after('primary_color')
                    ->comment('Hex color for brand theming');
            }

            // ═══════════════════════════════════════════════════════════════
            // HERO IMAGE: Cover/banner image for TenantHero component
            // NULLABLE - frontend has gradient fallback when null
            // Stored as relative path in storage
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'hero_image')) {
                $table->string('hero_image', 500)
                    ->nullable()
                    ->after('brand_color')
                    ->comment('Path to hero/banner image');
            }

            // ═══════════════════════════════════════════════════════════════
            // SLOGAN: Short brand tagline/subtitle
            // NULLABLE - optional marketing text
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'slogan')) {
                $table->string('slogan', 255)
                    ->nullable()
                    ->after('hero_image')
                    ->comment('Brand tagline/subtitle');
            }
        });

        // Sync primary_color to brand_color for existing records
        // This ensures UI shows correct color immediately
        \DB::statement("
            UPDATE tenants 
            SET brand_color = COALESCE(primary_color, '#2563EB')
            WHERE brand_color = '#2563EB' 
            AND primary_color IS NOT NULL 
            AND primary_color != ''
        ");
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['brand_color', 'hero_image', 'slogan']);
        });
    }
};
