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
        if (! Schema::hasTable('one_time_passwords')) {
            Schema::create('one_time_passwords', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index(); // Multi-tenancy
                $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('identifier')->index(); // Email or Phone
                $table->string('code'); // Hashed (HMAC-SHA256)
                $table->string('type'); // login, recovery, etc.
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->dateTime('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0); // Defense depth
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
