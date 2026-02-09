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
        if (Schema::hasTable('domain_outbox')) {
            return;
        }

        Schema::create('domain_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuid('aggregate_id')->nullable();
            $table->uuid('correlation_id')->index();
            $table->uuid('causation_id')->nullable();
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->string('status')->default('PENDING')->index();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_outbox');
    }
};
