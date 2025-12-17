<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Inventory System Migration
 *
 * Creates:
 * - menu_sections: Product categorization for tenants
 * - product_images: Multi-image gallery (PRO feature)
 *
 * Modifies:
 * - products: Adds menu_section_id, stock_status, is_featured, is_new
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // TABLE: menu_sections (Product Categories per Tenant)
        // ═══════════════════════════════════════════════════════════════════
        if (!Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->string('name', 255);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                // Composite index for optimized menu queries
                $table->index(['tenant_id', 'sort_order']);
            });
        }

        // ═══════════════════════════════════════════════════════════════════
        // TABLE: product_images (Gallery - PRO Feature)
        // ═══════════════════════════════════════════════════════════════════
        if (!Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->string('image_path', 2048);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['product_id', 'sort_order']);
            });
        }

        // ═══════════════════════════════════════════════════════════════════
        // ALTER: products (Add Advanced Inventory Fields - Safe)
        // ═══════════════════════════════════════════════════════════════════
        Schema::table('products', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('products', 'stock_status')) {
                $table->string('stock_status', 20)
                    ->default('in_stock')
                    ->after('price');
            }

            if (!Schema::hasColumn('products', 'is_new')) {
                $table->boolean('is_new')
                    ->default(false)
                    ->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        // Remove columns from products first (foreign key constraint)
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_section_id');
            $table->dropColumn(['stock_status', 'is_featured', 'is_new']);
        });

        Schema::dropIfExists('product_images');
        Schema::dropIfExists('menu_sections');
    }
};
