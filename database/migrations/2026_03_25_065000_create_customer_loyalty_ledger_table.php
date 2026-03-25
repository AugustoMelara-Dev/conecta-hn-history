<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_ledger', function (Blueprint $table): void {
            $table->id();
            $table->char('tenant_id', 36);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreignId('loyalty_reward_id')->nullable()->constrained('loyalty_rewards')->nullOnDelete();
            $table->char('created_by_user_id', 36)->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('type', 32);
            $table->integer('points_delta');
            $table->decimal('monetary_value', 10, 2)->default(0);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_profile_id', 'awarded_at'], 'customer_loyalty_profile_timeline_idx');
            $table->index(['tenant_id', 'type'], 'customer_loyalty_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_ledger');
    }
};
