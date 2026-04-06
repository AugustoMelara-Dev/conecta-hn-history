<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_journey_runs')) {
            return;
        }

        Schema::create('customer_journey_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_conversation_id')->constrained('customer_conversations')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->string('journey_key', 120);
            $table->string('segment_key', 64);
            $table->string('business_segment_key', 120)->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedSmallInteger('current_step_index')->default(0);
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('last_transition_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_conversation_id'], 'customer_journey_runs_conversation_unique');
            $table->index(['tenant_id', 'status', 'next_action_at'], 'customer_journey_runs_tenant_status_next_idx');
            $table->index(['tenant_id', 'segment_key', 'status'], 'customer_journey_runs_tenant_segment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_journey_runs');
    }
};
