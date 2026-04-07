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
        if (! Schema::hasTable('menu_sections')) {
            Schema::create('menu_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete(); // Denormalized for search
                $table->foreignId('menu_section_id')->nullable()->constrained()->nullOnDelete();

                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2);

                $table->string('image_path')->nullable();
                $table->json('gallery')->nullable(); // Multiple images
                $table->string('blur_hash')->nullable();

                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_locked')->default(false); // Admin moderation

                $table->json('variants')->nullable(); // Size, Color, etc.

                $table->timestamps();
                $table->softDeletes(); // Added SoftDeletes

                // Indexes
                $table->index(['tenant_id', 'is_active']);
                $table->index('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('products');
        Schema::dropIfExists('menu_sections');
        Schema::enableForeignKeyConstraints();
    }
};
