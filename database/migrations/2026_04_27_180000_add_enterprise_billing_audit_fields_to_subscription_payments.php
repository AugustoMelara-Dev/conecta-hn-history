<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_payments', 'receipt_issued_at')) {
                $table->timestamp('receipt_issued_at')->nullable()->after('subscription_extended_at');
            }

            if (! Schema::hasColumn('subscription_payments', 'refund_transaction_id')) {
                $table->string('refund_transaction_id')->nullable()->after('receipt_issued_at');
            }

            if (! Schema::hasColumn('subscription_payments', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_transaction_id');
            }

            if (! Schema::hasColumn('subscription_payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('refund_amount');
            }

            if (! Schema::hasColumn('subscription_payments', 'refund_reason')) {
                $table->string('refund_reason')->nullable()->after('refunded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table): void {
            foreach (['refund_reason', 'refunded_at', 'refund_amount', 'refund_transaction_id', 'receipt_issued_at'] as $column) {
                if (Schema::hasColumn('subscription_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
