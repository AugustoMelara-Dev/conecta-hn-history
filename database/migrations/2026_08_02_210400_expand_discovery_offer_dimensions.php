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
        Schema::table('discovery_published_offers', function (Blueprint $table): void {
            $table->foreignId('business_category_id')
                ->nullable()
                ->after('source_tenant_id');
            $table->string('business_category_slug', 190)
                ->nullable()
                ->after('business_category_id');

            $table->foreign('business_category_id', 'discover_offer_category_fk')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
            $table->index(
                ['country_code', 'business_category_slug', 'is_published', 'is_current', 'is_eligible'],
                'discover_category_lookup_idx',
            );
        });

        Schema::create('discovery_offer_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('projection_id');
            $table->foreignUuid('source_tenant_id');
            $table->foreignUuid('location_id');
            $table->char('country_code', 2);
            $table->foreignId('locality_id')->nullable();
            $table->string('locality_slug', 190)->nullable();
            $table->string('availability_status', 24)->default('unknown');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('projection_id', 'discover_offer_location_projection_fk')
                ->references('id')
                ->on('discovery_published_offers')
                ->cascadeOnDelete();
            $table->foreign('source_tenant_id', 'discover_offer_location_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('location_id', 'discover_offer_location_location_fk')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();
            $table->foreign('locality_id', 'discover_offer_location_locality_fk')
                ->references('id')
                ->on('localities')
                ->nullOnDelete();

            $table->unique(
                ['projection_id', 'location_id'],
                'discover_offer_location_unique',
            );
            $table->index(
                ['country_code', 'locality_slug', 'availability_status', 'projection_id'],
                'discover_offer_locality_lookup_idx',
            );
            $table->index(
                ['source_tenant_id', 'location_id'],
                'discover_offer_location_tenant_idx',
            );
        });
    }

    public function down(): void
    {
        // Dimensions are derivative and must disappear before their parent
        // category/index additions are removed.
        Schema::dropIfExists('discovery_offer_locations');

        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('discovery_published_offers', function (Blueprint $table) use ($sqlite): void {
            $table->dropIndex('discover_category_lookup_idx');
            $table->dropForeign($sqlite ? ['business_category_id'] : 'discover_offer_category_fk');
            $table->dropColumn(['business_category_id', 'business_category_slug']);
        });
    }
};
