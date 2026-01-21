<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription Payments - State Machine for Payment Lifecycle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            // UUID Primary Key to match Domain Entity usage and S3 Filename generation
            $table->uuid('id')->primary();
            
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('bank'); 
            $table->string('reference_number');
            $table->string('proof_path'); 
            $table->string('status')->default('pending'); 
            $table->text('admin_notes')->nullable(); 
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Prevent duplicate references per bank
            $table->unique(['bank', 'reference_number']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'subscription_status')) {
                $table->string('subscription_status')->default('active'); 
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'next_billing_date')) {
                $table->dropColumn('next_billing_date');
            }
            if (Schema::hasColumn('tenants', 'subscription_status')) {
                $table->dropColumn('subscription_status');
            }
        });
    }
};
