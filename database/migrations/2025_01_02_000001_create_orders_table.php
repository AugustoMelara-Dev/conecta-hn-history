<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Illuminate\Support\Facades\Schema::create('orders', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->nullable();
            $table->foreignIdFor(\App\Models\Tenant::class)->nullable();
            $table->string('order_number')->unique();
            $table->uuid('tracking_uuid')->nullable()->unique();

            // PII fields
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('customer_notes')->nullable();

            // Monetary
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Payment
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Source & Status
            $table->string('source')->nullable();
            $table->string('status')->default('pending');
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Workflow timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Illuminate\Support\Facades\Schema::dropIfExists('orders');
    }
};
