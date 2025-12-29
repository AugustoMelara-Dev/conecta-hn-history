<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add impulse buy flag to products for cross-selling.
 *
 * PERFORMANCE:
 * - Composite index on [business_id, is_impulse_buy] prevents full table scan
 * - Used with ID Fetch & Shuffle pattern (NO ORDER BY RAND())
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only add column if it doesn't exist
        if (Schema::hasColumn('products', 'is_impulse_buy')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_impulse_buy')->default(false)->after('is_active')
                ->comment('Low-cost add-on for cross-selling at checkout');

            // Composite index for performant impulse product queries
            $table->index(['tenant_id', 'is_impulse_buy'], 'idx_products_impulse');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_impulse');
            $table->dropColumn('is_impulse_buy');
        });
    }
};
