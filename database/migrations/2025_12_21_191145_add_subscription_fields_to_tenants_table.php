<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription Engine Migration
 *
 * Introduces temporal subscription management:
 * - subscription_status: State machine (active, trial, past_due, canceled)
 * - subscription_ends_at: Hard expiry date (null = perpetual)
 * - plan_type: Scalable plan identifier (semilla, pro, enterprise)
 *
 * BUSINESS LOGIC:
 * - is_pro is now COMPUTED, not stored
 * - Existing is_pro=true tenants get plan_type='pro'
 * - All tenants get active status to preserve service
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════
            // SUBSCRIPTION STATUS: Finite State Machine
            // active   = paid and current
            // trial    = evaluation period
            // past_due = payment pending (grace period)
            // canceled = subscription ended
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'subscription_status')) {
                $table->enum('subscription_status', ['active', 'trial', 'past_due', 'canceled'])
                    ->default('active')
                    ->after('is_active')
                    ->comment('Subscription state machine');
            }

            // ═══════════════════════════════════════════════════════════════
            // SUBSCRIPTION ENDS AT: Temporal hard stop
            // NULL = perpetual access (Semilla or lifetime)
            // Date = expiry deadline
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')
                    ->nullable()
                    ->after('subscription_status')
                    ->comment('NULL = perpetual, else hard expiry date');
            }

            // ═══════════════════════════════════════════════════════════════
            // PLAN TYPE: Scalable plan identifier
            // semilla    = Free tier with limitations
            // pro        = Paid tier with full features
            // enterprise = Future: White-label, multi-location, etc.
            // ═══════════════════════════════════════════════════════════════
            if (! Schema::hasColumn('tenants', 'plan_type')) {
                $table->enum('plan_type', ['semilla', 'pro', 'enterprise'])
                    ->default('semilla')
                    ->after('subscription_ends_at')
                    ->comment('Plan tier identifier');
            }
        });

        // Legacy data migration logic removed (is_pro does not exist in fresh schema)
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'subscription_ends_at', 'plan_type']);
        });
    }
};
