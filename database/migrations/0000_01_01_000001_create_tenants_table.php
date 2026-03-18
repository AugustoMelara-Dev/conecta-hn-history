<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index(); // Reduced constraint
            $table->string('name');
            $table->string('slug')->unique(); // domain
            $table->string('plan')->default('SEED'); // Added missing column
            $table->string('plan_type')->default('semilla');
            $table->text('description')->nullable();
            $table->uuid('city_id')->nullable()->index();
            $table->uuid('category_id')->nullable()->index();
            $table->string('subscription_status')->default('inactive');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->decimal('ai_balance', 15, 6)->default(0);
            $table->decimal('ai_reserved_balance', 15, 6)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
