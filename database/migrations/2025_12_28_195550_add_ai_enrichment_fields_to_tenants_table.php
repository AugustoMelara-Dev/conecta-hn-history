<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            // AI Enrichment Flag
            if (! Schema::hasColumn('tenants', 'is_ai_enhanced')) {
                $table->boolean('is_ai_enhanced')->default(false)->after('is_active');
            }
            // Admin notes for moderation and enrichment logging
            if (! Schema::hasColumn('tenants', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('is_ai_enhanced');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['is_ai_enhanced', 'admin_notes']);
        });
    }
};
