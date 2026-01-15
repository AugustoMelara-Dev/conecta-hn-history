<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create coupon_user pivot table for per-user usage tracking.
 *
 * RACE CONDITION PROTECTION:
 * - Composite index on [coupon_id, user_id] for rapid per-user lookup
 * - Used with lockForUpdate() in CouponValidatorService
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only create if table doesn't exist
        if (Schema::hasTable('coupon_user')) {
            return;
        }

        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coupon_id')
                ->constrained('coupons')
                ->cascadeOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Order association (nullable during validation, set on payment)
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->timestamp('redeemed_at')->useCurrent();

            // Composite index for rapid per-user lookup
            $table->index(['coupon_id', 'user_id'], 'idx_coupon_user_lookup');
        });

        // Add max_uses_per_user to coupons if missing
        if (! Schema::hasColumn('coupons', 'max_uses_per_user')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->unsignedInteger('max_uses_per_user')->default(1)->after('usage_limit');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');

        if (Schema::hasColumn('coupons', 'max_uses_per_user')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('max_uses_per_user');
            });
        }
    }
};
