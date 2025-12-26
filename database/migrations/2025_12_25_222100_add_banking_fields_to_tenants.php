<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Banking Information Fields to Tenants
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FINANCIAL ROUTING: Supports manual payment verification workflow       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BUSINESS CONTEXT:
 * Honduras has low credit card penetration. Businesses rely on bank transfers.
 * These fields allow each tenant to configure their own banking details,
 * which are then displayed to customers in the "Thank You" page.
 *
 * USAGE:
 * OrderResource->getPaymentInstructions() reads these fields from $order->tenant
 * to dynamically render bank transfer instructions in API responses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════════════
            // WHATSAPP OVERRIDE
            // ═══════════════════════════════════════════════════════════════════════
            // Allows tenant to specify a dedicated sales/support WhatsApp number
            // separate from their primary contact number.
            if (! Schema::hasColumn('tenants', 'whatsapp_number')) {
                $table->string('whatsapp_number', 20)->nullable()
                    ->after('whatsapp')
                    ->comment('Dedicated WhatsApp for payment verification');
            }

            // ═══════════════════════════════════════════════════════════════════════
            // BANK ACCOUNT DETAILS (Manual Transfer Support)
            // ═══════════════════════════════════════════════════════════════════════
            // These are displayed to customers when they select "Bank Transfer"
            // as their payment method. All fields are nullable for gradual onboarding.

            if (! Schema::hasColumn('tenants', 'bank_name')) {
                $table->string('bank_name', 50)->nullable()
                    ->comment('Bank institution name (e.g., BAC Credomatic, Banpaís)');
            }

            if (! Schema::hasColumn('tenants', 'bank_account')) {
                $table->string('bank_account', 50)->nullable()
                    ->comment('Bank account number');
            }

            if (! Schema::hasColumn('tenants', 'bank_account_name')) {
                $table->string('bank_account_name', 100)->nullable()
                    ->comment('Beneficiary name as registered with bank');
            }

            if (! Schema::hasColumn('tenants', 'bank_account_type')) {
                $table->string('bank_account_type', 20)->nullable()
                    ->comment('Account type: ahorro, corriente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [
                'whatsapp_number',
                'bank_name',
                'bank_account',
                'bank_account_name',
                'bank_account_type',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
