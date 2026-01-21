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
        Schema::table('subscription_payments', function (Blueprint $table) {
            // Partial Index for performance of "RescueStuckProofsCommand"
            // We only care about proofs starting with 'temp/'.
            // MySQL 8.0 support func index or prefix index.
            // Standard prefix index is safe.
            
            // "proof_path" is string (varchar 255).
            // Indexing the first 10 chars is enough to separate 'temp' from 'perm'.
            // proof_path LIKE 'temp/%' will use index if prefix length covers 'temp/'.
            
            // Raw SQL for prefix index if needed:
            // $table->index([DB::raw('proof_path(20)')], 'idx_proof_path_temp_prefix');
            
            // Laravel syntax:
            $table->index(['proof_path'], 'idx_proof_path_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropIndex('idx_proof_path_lookup');
        });
    }
};
