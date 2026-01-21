<?php

declare(strict_types=1);

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
        Schema::create('subscription_intents', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Serves as Global Idempotency Key');
            $table->uuid('tenant_id')->index();
            $table->string('plan_identifier');
            $table->string('gateway_provider');
            $table->string('status')->index()->comment('PENDING, PROCESSING, COMPLETED, FAILED');
            $table->string('gateway_transaction_id')->nullable();
            $table->text('error_reason')->nullable();
            
            // Critical for Scavenger Worker
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Foreign Key Constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_intents');
    }
};
