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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Key: Unique identifier for the operation
            // Indexed/Unique for lock atomicity
            $table->string('key', 191)->unique();

            // Checksum: Integrity Guard (MD5/SHA)
            $table->string('checksum', 64);

            // Result: Nullable because it's empty during "Processing" phase
            // Mapped to 'response_payload' in Model/Repository
            $table->json('response_payload')->nullable();

            // Timestamps: Created_at is critical for Zombie detection
            $table->timestamps();

            // Optimization: Index for zombie cleanup (optional but good)
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
