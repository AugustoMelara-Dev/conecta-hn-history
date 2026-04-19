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
        Schema::create('directory_guide_request_metrics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->index();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('status_code')->index();
            $table->string('ranking_mode', 32)->nullable()->index();
            $table->string('reasoning_basis', 64)->nullable();
            $table->string('degraded_reason', 64)->nullable()->index();
            $table->boolean('degraded_to_search')->default(false)->index();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('tenant_count')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('quota_limit')->nullable();
            $table->unsignedInteger('quota_used')->nullable();
            $table->unsignedInteger('quota_remaining')->nullable();
            $table->timestamp('quota_reset_at')->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('search', 120)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status_code'], 'directory_guide_metrics_created_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_guide_request_metrics');
    }
};
