<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEVEL 44: Partitioned Outbox Schema
 * 
 * Adds aggregate affinity columns for sequence-preserving parallel processing.
 * - aggregate_id: Groups events by business entity (e.g., payment_id)
 * - partition_key: Deterministic partition for worker sharding
 * - sequence_number: Monotonic sequence per aggregate for ordering
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            // LEVEL 44: Aggregate Affinity Columns
            $table->string('aggregate_id', 36)->nullable()->after('type')
                ->comment('Business entity ID for sequence grouping');
            
            $table->unsignedSmallInteger('partition_key')->default(0)->after('aggregate_id')
                ->comment('Deterministic partition for worker sharding (crc32 % N)');
            
            $table->unsignedBigInteger('sequence_number')->default(0)->after('partition_key')
                ->comment('Monotonic sequence per aggregate for ordering');

            // Composite index for partition-aware queries and Level 54 Sequence Barrier
            // Optimized for: WHERE partition_key=? AND status=? ORDER BY sequence_number
            $table->index(['partition_key', 'status', 'sequence_number'], 'idx_outbox_partition_sequence');
            
            // Index for aggregate sequence verification
            $table->index(['aggregate_id', 'status'], 'idx_outbox_aggregate_status');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_partition_query');
            $table->dropIndex('idx_outbox_aggregate_status');
            $table->dropColumn(['aggregate_id', 'partition_key', 'sequence_number']);
        });
    }
};
