<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEVEL 50.1: Add worker_id column for Atomic Grab pattern.
 *
 * This column stores the owning worker ID for race condition elimination.
 * Required for deterministic stale-lock recovery and duplicate billing prevention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            // Worker ID for atomic ownership assignment
            $table->string('worker_id', 128)->nullable()->after('locked_at');

            // Batch correlation ID for telemetric proof of ownership
            $table->uuid('batch_correlation_id')->nullable()->after('worker_id');

            // Index for fast worker-specific lookups
            $table->index(['worker_id', 'status'], 'idx_outbox_worker_status');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_worker_status');
            $table->dropColumn(['worker_id', 'batch_correlation_id']);
        });
    }
};
