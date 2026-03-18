<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // Event Class Name
            $table->json('payload'); // Serialized Data
            $table->string('aggregate_id')->nullable();
            $table->integer('partition_key')->default(0);
            $table->bigInteger('sequence_number')->default(0);
            $table->string('batch_correlation_id')->nullable();
            $table->string('worker_id')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('quarantined_at')->nullable();
            $table->integer('recovery_count')->default(0);
            $table->text('error')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('published_at')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, PROCESSING, COMPLETED, FAILED, PENDING_AUTOPSY
            $table->timestamps();

            // High availability lease and recovery indexes.
            $table->index(['status', 'partition_key', 'sequence_number']);
            $table->index(['worker_id', 'status', 'batch_correlation_id'], 'idx_outbox_worker_batch');
            $table->index(['aggregate_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
