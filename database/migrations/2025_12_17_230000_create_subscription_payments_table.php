<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription Payments - State Machine for Payment Lifecycle
 *
 * State flow: pending → approved/rejected
 *
 * Creates:
 * - subscription_payments: Payment transaction history
 *
 * Modifies:
 * - tenants: Adds billing cycle fields if missing
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // TABLE: subscription_payments
        // ═══════════════════════════════════════════════════════════════════
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('bank'); // BAC, Ficohsa, Atlantida, Occidente
            $table->string('reference_number');
            $table->string('proof_path'); // Path to image (private disk)
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('admin_notes')->nullable(); // Rejection reason
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Prevent duplicate references per bank
            $table->unique(['bank', 'reference_number']);
        });

        // ═══════════════════════════════════════════════════════════════════
        // ALTER: tenants (Billing Cycle Fields)
        // ═══════════════════════════════════════════════════════════════════
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'subscription_status')) {
                $table->string('subscription_status')->default('active'); // active, past_due, cancelled
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
