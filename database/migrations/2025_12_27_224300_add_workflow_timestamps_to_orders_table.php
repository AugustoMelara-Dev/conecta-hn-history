<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Workflow Timestamps to Orders Table
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FSM SCHEMA SYNC: Columns required by OrderStateService                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * The Finite State Machine records workflow metrics for SLA tracking:
 * - processing_started_at: When kitchen/preparation begins
 * - ready_at: When order is ready for pickup/delivery
 *
 * These timestamps enable operational analytics:
 * - Average preparation time
 * - Ready-to-delivery latency
 * - SLA compliance monitoring
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // SAFETY: Ensure dependency exists
            if (! Schema::hasColumn('orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('orders', 'processing_started_at')) {
                $table->timestamp('processing_started_at')
                    ->nullable()
                    ->after('confirmed_at')
                    ->comment('When order entered processing/preparation');
            }

            if (! Schema::hasColumn('orders', 'ready_at')) {
                $table->timestamp('ready_at')
                    ->nullable()
                    ->after('processing_started_at')
                    ->comment('When order became ready for pickup/delivery');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['processing_started_at', 'ready_at']);
        });
    }
};
