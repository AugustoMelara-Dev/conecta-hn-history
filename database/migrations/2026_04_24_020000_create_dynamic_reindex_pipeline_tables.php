<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_reindex_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->uuid('entity_definition_id');
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('chunk_size')->default(200);
            $table->unsignedBigInteger('total_records')->default(0);
            $table->unsignedBigInteger('processed_records')->default(0);
            $table->unsignedBigInteger('failed_records')->default(0);
            $table->uuid('last_record_id')->nullable();
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('entity_definition_id')
                ->references('id')
                ->on('entity_definitions')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'entity_definition_id', 'status'], 'dyn_reindex_runs_scope_status_idx');
            $table->index(['status', 'created_at'], 'dyn_reindex_runs_status_created_idx');
        });

        Schema::create('dynamic_reindex_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_reindex_run_id');
            $table->unsignedInteger('chunk_number');
            $table->string('status', 24)->default('running');
            $table->uuid('cursor_start')->nullable();
            $table->uuid('cursor_end')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('dynamic_reindex_run_id')
                ->references('id')
                ->on('dynamic_reindex_runs')
                ->cascadeOnDelete();

            $table->unique(['dynamic_reindex_run_id', 'chunk_number'], 'dyn_reindex_checkpoint_run_chunk_unique');
            $table->index(['dynamic_reindex_run_id', 'status'], 'dyn_reindex_checkpoint_run_status_idx');
            $table->index(['dynamic_reindex_run_id', 'processed_at'], 'dyn_reindex_checkpoint_run_processed_idx');
        });

        Schema::create('dynamic_reindex_failures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dynamic_reindex_run_id');
            $table->foreignId('dynamic_reindex_checkpoint_id')->nullable()->constrained('dynamic_reindex_checkpoints')->nullOnDelete();
            $table->uuid('dynamic_record_id')->nullable();
            $table->unsignedInteger('chunk_number');
            $table->text('error_message');
            $table->json('context_json')->nullable();
            $table->timestamp('failed_at');
            $table->timestamps();

            $table->foreign('dynamic_reindex_run_id')
                ->references('id')
                ->on('dynamic_reindex_runs')
                ->cascadeOnDelete();

            $table->index(['dynamic_reindex_run_id', 'chunk_number'], 'dyn_reindex_failure_run_chunk_idx');
            $table->index(['dynamic_reindex_run_id', 'dynamic_record_id'], 'dyn_reindex_failure_run_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_reindex_failures');
        Schema::dropIfExists('dynamic_reindex_checkpoints');
        Schema::dropIfExists('dynamic_reindex_runs');
    }
};

