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
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('identifier')->index(); // Phone or email
            $table->string('code'); // Hashed 6-digit code
            $table->timestamp('expires_at')->index();
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Composite index for faster lookups
            $table->index(['identifier', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
