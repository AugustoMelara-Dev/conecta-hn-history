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
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) {
            // LEVEL 44: Aggregate Affinity Columns
            if (! Schema::hasColumn('outbox_messages', 'aggregate_id')) {
                $table->string('aggregate_id', 36)->nullable()
                    ->comment('Business entity ID for sequence grouping');
            }

            if (! Schema::hasColumn('outbox_messages', 'partition_key')) {
                $table->unsignedSmallInteger('partition_key')->default(0)
                    ->comment('Deterministic partition for worker sharding (crc32 % N)');
            }

            if (! Schema::hasColumn('outbox_messages', 'sequence_number')) {
                $table->unsignedBigInteger('sequence_number')->default(0)
                    ->comment('Monotonic sequence per aggregate for ordering');
            }
        });

        // Add indexes only if they don't exist
        $indexes = collect(Schema::getIndexes('outbox_messages'))->pluck('name');

        if (! $indexes->contains('idx_outbox_partition_sequence')) {
            Schema::table('outbox_messages', function (Blueprint $table) {
                $table->index(['partition_key', 'status', 'sequence_number'], 'idx_outbox_partition_sequence');
            });
        }

        if (! $indexes->contains('idx_outbox_aggregate_status')) {
            Schema::table('outbox_messages', function (Blueprint $table) {
                $table->index(['aggregate_id', 'status'], 'idx_outbox_aggregate_status');
            });
        }
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
