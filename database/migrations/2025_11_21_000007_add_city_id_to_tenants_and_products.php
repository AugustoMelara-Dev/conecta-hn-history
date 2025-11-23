<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('slug')
                ->constrained('cities')
                ->nullOnDelete();
            
            $table->index('city_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('cities')
                ->nullOnDelete();
            
            $table->index('city_id');
        });

        // OPTION 1: Migrate existing data to San Pedro Sula (city_id = 1)
        // This runs AFTER the GeoSeeder has populated cities
        // We'll do this in a separate step after seeding
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
