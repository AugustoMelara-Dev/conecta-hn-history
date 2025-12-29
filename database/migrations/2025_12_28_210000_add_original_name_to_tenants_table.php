<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add original_name column to preserve tenant names before AI enrichment.
 * This enables rollback if AI-generated name is rejected by tenant owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable()->after('name')
                ->comment('Backup of user-provided name before AI enrichment');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};
