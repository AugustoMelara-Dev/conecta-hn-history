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
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) {
            // Worker ID for atomic ownership assignment
            if (! Schema::hasColumn('outbox_messages', 'worker_id')) {
                $table->string('worker_id', 128)->nullable();
            }

            // Batch correlation ID for telemetric proof of ownership
            if (! Schema::hasColumn('outbox_messages', 'batch_correlation_id')) {
                $table->uuid('batch_correlation_id')->nullable();
            }
        });

        // Add index only if it doesn't exist
        $indexExists = collect(Schema::getIndexes('outbox_messages'))
            ->contains(fn($idx) => $idx['name'] === 'idx_outbox_worker_status');

        if (! $indexExists) {
            Schema::table('outbox_messages', function (Blueprint $table) {
                $table->index(['worker_id', 'status'], 'idx_outbox_worker_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_worker_status');
            $table->dropColumn(['worker_id', 'batch_correlation_id']);
        });
    }
};
