<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Payment Tracking Fields to Orders
 *
 * Stores transaction IDs and timestamps from payment webhooks
 * for financial reconciliation and audit trails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════════════
            // PAYMENT CONFIRMATION
            // ═══════════════════════════════════════════════════════════════════════
            // External transaction ID from payment provider (Stripe: pi_xxx, PayPal: xxx)
            // Used for idempotency checks and financial reconciliation
            if (! Schema::hasColumn('orders', 'payment_transaction_id')) {
                $table->string('payment_transaction_id')->nullable()
                    ->after('payment_method')
                    ->comment('External payment provider transaction ID');
            }

            // Timestamp when payment was confirmed via webhook
            if (! Schema::hasColumn('orders', 'payment_confirmed_at')) {
                $table->timestamp('payment_confirmed_at')->nullable()
                    ->after('payment_transaction_id')
                    ->comment('When payment was confirmed by webhook');
            }

            // ═══════════════════════════════════════════════════════════════════════
            // REFUND TRACKING
            // ═══════════════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('orders', 'refund_transaction_id')) {
                $table->string('refund_transaction_id')->nullable()
                    ->after('payment_confirmed_at')
                    ->comment('External refund transaction ID');
            }

            if (! Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()
                    ->after('refund_transaction_id')
                    ->comment('When refund was processed');
            }
        });

        // Index for transaction lookups
        if (! Schema::hasIndex('orders', 'orders_payment_transaction_id_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('payment_transaction_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'payment_transaction_id',
                'payment_confirmed_at',
                'refund_transaction_id',
                'refunded_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
