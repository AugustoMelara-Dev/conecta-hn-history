<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // LEVEL 69: GAP LOCK NEUTRALIZATION
        // We use raw SQL to enforce specific index algorithm and locking strategy if supported by the driver.
        // The composite index is strictly ordered for the "Pivot" query in claimBatch.
        
        Schema::table('outbox_messages', function (Blueprint $table) {
            // Drop existing index if it conflicts or is redundant (optional, assuming clean state or defensive)
            // $table->dropIndex(['partition_key', 'sequence_number']); 
            
            // Create the "Pivot Index"
            // 1. status: Equality check ('PENDING')
            // 2. partition_key: Equality check (IN list)
            // 3. recovery_count: Range check (< threshold) - acts as covering field too
            // 4. sequence_number: Sort order
            $table->index(
                ['status', 'partition_key', 'recovery_count', 'sequence_number'], 
                'idx_outbox_claim_optimization'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_claim_optimization');
        });
    }
};
