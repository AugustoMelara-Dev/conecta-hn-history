<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Coupon Fields to Orders Table
 *
 * Stores the snapshot of applied discount for audit trail.
 * Even if coupon is deleted later, order history is preserved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Coupon code used (snapshot - preserved even if coupon deleted)
            if (! Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 50)->nullable()->after('total');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'coupon_code')) {
                $table->dropColumn('coupon_code');
            }
        });
    }
};
