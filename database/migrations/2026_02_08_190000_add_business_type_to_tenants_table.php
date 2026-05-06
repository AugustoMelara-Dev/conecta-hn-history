<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add business_type discriminator to tenants table
 * 
 * PURPOSE: Enable feature toggling between Commerce (retail/inventory)
 * and Service (appointments/calendar) business models.
 * 
 * BACKWARD COMPATIBILITY: Default 'retail' ensures existing tenants
 * continue to function without modification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('business_type')->default('retail')->after('plan_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
