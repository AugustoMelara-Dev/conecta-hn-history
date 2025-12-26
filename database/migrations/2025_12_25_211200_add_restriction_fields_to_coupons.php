<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Product/Category Restriction Fields to Coupons
 *
 * Enables context-aware coupon validation:
 * - Restrict coupon to specific categories
 * - Restrict coupon to specific products
 * - Calculate discounts only on eligible items
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════════════
            // SCOPE RESTRICTIONS (JSON Arrays)
            // ═══════════════════════════════════════════════════════════════════════
            // null = applies to all products/categories
            // [1, 2, 3] = only applies to these specific IDs
            //
            // When both are set, product takes precedence (AND logic)
            // When empty array, restriction is ignored

            // Category IDs this coupon is restricted to (menu_section_id)
            $table->json('restricted_to_categories')->nullable()
                ->comment('JSON array of category IDs, null = all categories');

            // Product IDs this coupon is restricted to
            $table->json('restricted_to_products')->nullable()
                ->comment('JSON array of product IDs, null = all products');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['restricted_to_categories', 'restricted_to_products']);
        });
    }
};
