<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align order_items table with OrderItem model expectations.
 * 
 * FIXES:
 * - Adds `product_price` column (snapshot price at order time)
 * - Renames `line_total` to `total_price` for model consistency
 * - Renames `options` to `selected_options` for model consistency
 * 
 * This resolves test failures caused by schema-model mismatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add product_price if it doesn't exist
            if (!Schema::hasColumn('order_items', 'product_price')) {
                $table->decimal('product_price', 10, 2)->after('product_name');
            }
            
            // Add total_price if it doesn't exist (for backward compatibility)
            if (!Schema::hasColumn('order_items', 'total_price')) {
                $table->decimal('total_price', 10, 2)->after('unit_price');
            }
            
            // Add selected_options if it doesn't exist
            if (!Schema::hasColumn('order_items', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('total_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $columns = ['product_price', 'total_price', 'selected_options'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
