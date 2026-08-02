<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_billing_credit_reservations', function (Blueprint $table): void {
            $table->dropUnique('global_billing_credit_reservations_idempotency_key_unique');
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'global_billing_credit_reservations_tenant_idempotency_unique',
            );
        });

        Schema::table('global_billing_credit_ledger_entries', function (Blueprint $table): void {
            $table->dropUnique('global_billing_credit_ledger_entries_idempotency_key_unique');
            $table->unique(
                ['tenant_id', 'idempotency_key'],
                'global_billing_credit_ledger_entries_tenant_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Program 09 billing idempotency scope migration is forward-only.');
    }
};
