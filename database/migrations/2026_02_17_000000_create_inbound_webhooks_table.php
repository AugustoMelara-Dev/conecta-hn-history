<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index(); // Logical separation
            $table->string('provider');
            $table->string('event_type');
            $table->string('provider_transaction_id');
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Idempotency Constraint
            $table->unique(['provider', 'provider_transaction_id'], 'unique_webhook_provider_tx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhooks');
    }
};
