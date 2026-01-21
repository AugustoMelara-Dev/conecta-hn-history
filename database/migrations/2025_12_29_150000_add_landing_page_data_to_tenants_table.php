<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add landing_page_data JSON column to tenants table.
 *
 * CONSTRAINT: Column MUST be nullable to prevent integrity violations
 * on existing production records during deployment.
 *
 * Application layer handles null as empty object: { sections: [] }
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // NULLABLE JSON - Safe schema evolution
            // Removed ->after('theme_config') - column may not exist in all environments
            $table->json('landing_page_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('landing_page_data');
        });
    }
};
