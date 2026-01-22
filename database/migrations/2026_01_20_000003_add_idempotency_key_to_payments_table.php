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
        Schema::table('subscription_payments', function (Blueprint $table) {
            // The Client's Idempotency Key (Fingerprint)
            $table->string('idempotency_key')->after('tenant_id');

            // The Mathematical Constraint (Composite Unique Index)
            // 1 Tenant + 1 Key = 1 Payment.
            $table->unique(['tenant_id', 'idempotency_key'], 'unique_payment_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropUnique('unique_payment_idempotency');
            $table->dropColumn('idempotency_key');
        });
    }
};
