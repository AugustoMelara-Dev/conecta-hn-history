<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add free_shipping to coupon types
 *
 * This migration adds the 'free_shipping' discount type to support
 * promotional coupons that waive shipping costs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the column
        // For MySQL/PostgreSQL, we can use ALTER
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN for enums
            // We need to: 1) create new column, 2) copy data, 3) drop old, 4) rename
            Schema::table('coupons', function (Blueprint $table) {
                $table->string('type_new', 20)->default('fixed')->after('description');
            });

            // Copy existing data
            DB::table('coupons')->update([
                'type_new' => DB::raw('type'),
            ]);

            // Drop old column and rename new one
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('coupons', function (Blueprint $table) {
                $table->renameColumn('type_new', 'type');
            });
        } else {
            // MySQL/PostgreSQL
            DB::statement("ALTER TABLE coupons MODIFY COLUMN type ENUM('fixed', 'percent', 'free_shipping') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Convert any free_shipping back to fixed before changing column
            DB::table('coupons')
                ->where('type', 'free_shipping')
                ->update(['type' => 'fixed']);
        } else {
            // MySQL/PostgreSQL
            DB::table('coupons')
                ->where('type', 'free_shipping')
                ->update(['type' => 'fixed']);

            DB::statement("ALTER TABLE coupons MODIFY COLUMN type ENUM('fixed', 'percent') NOT NULL");
        }
    }
};
