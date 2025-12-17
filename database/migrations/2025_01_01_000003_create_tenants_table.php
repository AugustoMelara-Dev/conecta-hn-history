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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('address')->nullable();

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('blur_hash')->nullable();
            $table->string('primary_color')->default('#000000');
            $table->json('theme_config')->nullable();

            // Contact & Social
            $table->string('whatsapp_number')->nullable();
            $table->text('whatsapp_message')->nullable();
            $table->text('whatsapp_template')->nullable(); // Legacy? Keeping for safety
            $table->json('social_links')->nullable();

            // Status & Config
            $table->boolean('is_pro')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending'); // pending, active, suspended
            $table->timestamp('plan_expires_at')->nullable();
            $table->integer('product_limit')->default(10);

            // Operational
            $table->json('hours_data')->nullable(); // opening_hours

            $table->timestamps();
            $table->softDeletes(); // Added SoftDeletes

            // Indexes
            $table->index(['slug', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
