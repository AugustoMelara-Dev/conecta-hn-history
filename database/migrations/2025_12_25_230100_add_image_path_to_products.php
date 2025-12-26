<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Image Storage Path to Products
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  VISUAL ASSET MANAGEMENT: Product images for E-Commerce catalog        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * STORAGE STRATEGY:
 * - Images stored in storage/app/public/products/
 * - Accessed via public/storage symlink (php artisan storage:link)
 * - URL generated via Storage::url($product->image_path)
 *
 * PATH FORMAT:
 * - Internal: products/uuid.jpg
 * - Public URL: /storage/products/uuid.jpg
 * - Full URL: https://domain.com/storage/products/uuid.jpg
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════════════
            // IMAGE PATH (Relative to storage/app/public/)
            // ═══════════════════════════════════════════════════════════════════════
            // Length: 2048 chars supports long paths and external URLs
            // Nullable: Legacy products and text-only items
            if (! Schema::hasColumn('products', 'image_path')) {
                $table->string('image_path', 2048)->nullable()
                    ->after('description')
                    ->comment('Relative path to image in storage/app/public/');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
