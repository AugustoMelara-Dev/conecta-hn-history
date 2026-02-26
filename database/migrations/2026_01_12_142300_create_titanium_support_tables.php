<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table 1: Idempotency Keys (Concurrency Control)
        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->string('key', 64)->primary(); // Checksum/UUID
                $table->json('response_payload'); // Serialized Result
                $table->timestamps();

                // Optimization: Index for cleanup
                $table->index('created_at');
            });
        }


    }

    public function down(): void
    {

        Schema::dropIfExists('idempotency_keys');
    }
};
