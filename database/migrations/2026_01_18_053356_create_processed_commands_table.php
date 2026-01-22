<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processed_commands', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // Support transactions

            // Primary Key: The Mutex Barrier
            $table->string('idempotency_key', 36)->primary();

            // Tenant Partitioning
            $table->uuid('tenant_id')->index();

            // Integrity Check: SHA-256 Hash of the command body
            $table->string('command_hash', 64);

            // State Machine
            $table->enum('status', ['PROCESSING', 'COMPLETED', 'FAILED']);

            // Result Storage
            $table->json('response_payload')->nullable();

            // Audit Timestamps
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            } else {
                $table->timestamp('created_at', 6)->nullable();
                $table->timestamp('updated_at', 6)->nullable();
            }

            // DLM Index: Optimized for daily pruning (WHERE status='COMPLETED' AND created_at < ?)
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_commands');
    }
};
