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
        Schema::create('processed_commands', function (Blueprint $table) {
            $table->string('idempotency_key')->primary();
            $table->uuid('tenant_id')->index();
            $table->integer('status_code');
            $table->json('payload')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
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
