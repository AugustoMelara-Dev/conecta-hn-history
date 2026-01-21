<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // Event Class Name
            $table->json('payload'); // Serialized Data
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, PUBLISHED, FAILED
            $table->timestamps();

            // Critical Index for Polling Performance
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
