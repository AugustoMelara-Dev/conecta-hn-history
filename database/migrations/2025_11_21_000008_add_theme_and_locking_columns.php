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
        Schema::table('tenants', function (Blueprint $table) {
            // Theme configuration (CSS variables)
            $table->json('theme_config')->nullable()->after('description');

            // Product limit (default: 10, manual control for Pro plans)
            $table->integer('product_limit')->default(10)->after('theme_config');
        });

        Schema::table('products', function (Blueprint $table) {
            // Soft-locking for subscription downgrades
            $table->boolean('is_locked')->default(false)->index()->after('is_featured');
        });

        // Set default theme for existing tenants
        DB::table('tenants')->update([
            'theme_config' => json_encode([
                'primary_color' => '#000000',
                'radius' => '0.5rem',
                'font' => 'Inter',
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['theme_config', 'product_limit']);
        });
    }
};
