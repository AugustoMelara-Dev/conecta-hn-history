<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Operational Settings to Tenants Table
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CHECKOUT CONFIGURATION: Payment & Delivery Options                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * These columns enable tenant-level customization of:
 * - Available payment methods (cash, transfer, card, etc.)
 * - Delivery/pickup toggles
 * - Minimum order requirements
 *
 * FRONTEND USAGE:
 * Injected via HandleInertiaRequests middleware as:
 * usePage().props.tenant.settings.payment_methods
 * usePage().props.tenant.settings.delivery_enabled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════
            // PAYMENT CONFIGURATION
            // ═══════════════════════════════════════════════════════════════
            // JSON array of enabled payment methods
            // Example: ["cash", "transfer", "card"]
            // Empty array = all methods disabled (cash-only default)
            $table->json('payment_methods')
                ->nullable()
                ->after('bank_account_type')
                ->comment('Enabled payment gateways: cash, transfer, card, etc.');

            // ═══════════════════════════════════════════════════════════════
            // FULFILLMENT OPTIONS
            // ═══════════════════════════════════════════════════════════════
            $table->boolean('delivery_enabled')
                ->default(true)
                ->after('payment_methods')
                ->comment('Toggle delivery option in checkout');

            $table->boolean('pickup_enabled')
                ->default(true)
                ->after('delivery_enabled')
                ->comment('Toggle pickup option in checkout');

            // ═══════════════════════════════════════════════════════════════
            // ORDER CONSTRAINTS
            // ═══════════════════════════════════════════════════════════════
            // Minimum order value (0 = no minimum)
            $table->decimal('minimum_order', 10, 2)
                ->default(0)
                ->after('pickup_enabled')
                ->comment('Minimum order value in local currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'payment_methods',
                'delivery_enabled',
                'pickup_enabled',
                'minimum_order',
            ]);
        });
    }
};
