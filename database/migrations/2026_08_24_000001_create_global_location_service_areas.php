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
        Schema::table('locations', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'locations_tenant_id_id_unique');
        });

        Schema::create('location_service_areas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('location_id');
            $table->string('fulfillment_mode', 24);
            $table->string('area_type', 32);
            $table->char('country_code', 2);
            $table->foreignId('administrative_area_id')->nullable()->constrained('administrative_areas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('localities')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('radius_km', 7, 2)->nullable();
            $table->string('coverage_key', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['tenant_id', 'location_id'], 'coverage_location_tenant_fk')
                ->references(['tenant_id', 'id'])->on('locations')->cascadeOnDelete();
            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->unique(['tenant_id', 'location_id', 'fulfillment_mode', 'coverage_key'], 'coverage_mode_area_unique');
            $table->index(['tenant_id', 'fulfillment_mode', 'is_active'], 'coverage_tenant_mode_active_idx');
            $table->index(['country_code', 'administrative_area_id', 'locality_id'], 'coverage_geography_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "location_service_areas" ADD CONSTRAINT "coverage_mode_check" CHECK (fulfillment_mode IN (\'storefront\', \'delivery\', \'on_site\', \'remote\', \'pickup\'))');
            DB::statement('ALTER TABLE "location_service_areas" ADD CONSTRAINT "coverage_area_type_check" CHECK (area_type IN (\'locality\', \'administrative_area\', \'radius\', \'national\'))');
            DB::statement('ALTER TABLE "location_service_areas" ADD CONSTRAINT "coverage_shape_check" CHECK ((area_type = \'locality\' AND locality_id IS NOT NULL AND administrative_area_id IS NULL AND radius_km IS NULL) OR (area_type = \'administrative_area\' AND administrative_area_id IS NOT NULL AND locality_id IS NULL AND radius_km IS NULL) OR (area_type = \'radius\' AND locality_id IS NOT NULL AND administrative_area_id IS NULL AND radius_km > 0 AND radius_km <= 500) OR (area_type = \'national\' AND locality_id IS NULL AND administrative_area_id IS NULL AND radius_km IS NULL))');
            DB::statement('ALTER TABLE "location_service_areas" ENABLE ROW LEVEL SECURITY');
            DB::statement('CREATE POLICY "location_service_areas_tenant_isolation" ON "location_service_areas" USING (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid) WITH CHECK (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_service_areas');
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropUnique('locations_tenant_id_id_unique');
        });
    }
};
