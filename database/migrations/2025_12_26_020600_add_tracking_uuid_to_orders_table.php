<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Tracking UUID to Orders Table - Cryptographic WebSocket Security
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: High-entropy token for public order tracking                 ║
 * ║  REPLACES: Sequential IDs which are vulnerable to enumeration           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USE CASE:
 * Customers can track their order status via WebSocket without authentication.
 * The UUID acts as a bearer token - only those with the UUID can subscribe.
 *
 * SECURITY:
 * - UUID v4 has 122 bits of entropy (2^122 possible values)
 * - Probability of guessing: ~1 in 5.3 undecillion
 * - Unique index ensures O(1) lookup during authorization
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'tracking_uuid')) {
            return; // Column already exists (created in initial migration)
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('tracking_uuid')
                ->nullable()
                ->after('order_number')
                ->unique()
                ->comment('Cryptographic token for public order tracking');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_uuid');
        });
    }
};
