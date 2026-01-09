<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domain_outbox_events', function (Blueprint $table) {
            // ACID Guarantee: InnoDB engine required for transactional integrity
            $table->engine = 'InnoDB';

            $table->uuid('id')->primary();
            
            // Polymorphic Event Class Name (FQCN)
            $table->string('type');
            
            // Serialized payload (JSON) - Capable of storing complex objects
            $table->longText('payload');
            
            // Status tracking
            $table->string('status')->index()->default('PENDING'); 
            // Enum: PENDING, PROCESSING, FAILED, PUBLISHED
            
            // Audit Timestamps
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            
            // Debugging
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Compound index for efficient worker polling
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_outbox_events');
    }
};
