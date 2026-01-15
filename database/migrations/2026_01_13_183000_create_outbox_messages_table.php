<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('outbox_messages')) {
            Schema::create('outbox_messages', function (Blueprint $table) {
                // UUID for distributed safety
                $table->uuid('id')->primary();
                
                // Public Event Name (e.g., Identity.UserRegistered.v1)
                $table->string('type');
                
                // Serialized Payload (JSON)
                $table->json('payload');
                
                // Timeline
                $table->timestamp('occurred_at', 6);
                $table->timestamp('processed_at', 6)->nullable()->index();
                
                // Optional: Aggregate ID for ordering consumers
                //$table->string('aggregate_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
