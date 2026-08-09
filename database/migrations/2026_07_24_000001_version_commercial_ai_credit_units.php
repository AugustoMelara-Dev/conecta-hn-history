<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_credit_reservations', function (Blueprint $table): void {
            $table->string('denomination', 32)->default('legacy_provider_usd')->after('actual_cost');
            $table->string('pricing_version', 32)->nullable()->after('denomination');
            $table->decimal('credits_per_provider_usd', 12, 6)->nullable()->after('pricing_version');
            $table->decimal('minimum_charge', 12, 6)->nullable()->after('credits_per_provider_usd');
            $table->index(['tenant_id', 'denomination', 'status', 'created_at'], 'ai_credit_reservations_unit_usage_idx');
        });

        Schema::table('ai_executions', function (Blueprint $table): void {
            $table->decimal('charged_credits', 12, 6)->nullable()->after('charged_cost');
            $table->string('credit_pricing_version', 32)->nullable()->after('charged_credits');
            $table->decimal('credits_per_provider_usd', 12, 6)->nullable()->after('credit_pricing_version');
            $table->decimal('credit_minimum_charge', 12, 6)->nullable()->after('credits_per_provider_usd');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('ai_credit_unit_version', 32)->nullable()->after('ai_reserved_balance');
        });

        $now = Carbon::now('UTC');
        $period = $now->format('Y-m');
        $pricingVersion = trim((string) config('ai.commercial_credits.pricing_version', 'credits-v1')) ?: 'credits-v1';

        DB::table('ai_executions')
            ->where('status', 'accounting_pending')
            ->whereNull('charged_credits')
            ->update([
                'status' => 'failed',
                'failure_code' => 'legacy_credit_unit_cutover',
                'failed_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('ai_credit_reservations')
            ->where('status', 'pending')
            ->where('denomination', 'legacy_provider_usd')
            ->update([
                'status' => 'refunded',
                'failure_reason' => 'legacy_credit_unit_cutover',
                'refunded_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('tenants')
            ->select(['id', 'plan_type', 'subscription_status', 'subscription_ends_at', 'ai_balance', 'ai_reserved_balance'])
            ->orderBy('id')
            ->chunk(200, function ($tenants) use ($now, $period, $pricingVersion): void {
                foreach ($tenants as $tenant) {
                    $plan = $this->effectivePlan($tenant, $now);
                    $allowance = (float) config("subscriptions.plans.{$plan}.limits.ai_monthly_credits", 1.0);
                    $previousBalance = (float) $tenant->ai_balance;
                    $previousReserved = (float) $tenant->ai_reserved_balance;
                    $sequence = ((int) DB::table('ai_credit_grants')
                        ->where('tenant_id', (string) $tenant->id)
                        ->where('billing_period', $period)
                        ->max('transition_sequence')) + 1;
                    $grantId = (string) Str::uuid();

                    DB::table('tenants')->where('id', $tenant->id)->update([
                        'ai_balance' => $allowance,
                        'ai_reserved_balance' => 0,
                        'ai_credit_unit_version' => $pricingVersion,
                        'updated_at' => $now,
                    ]);

                    DB::table('ai_credit_grants')->insert([
                        'id' => $grantId,
                        'tenant_id' => (string) $tenant->id,
                        'billing_period' => $period,
                        'plan' => $plan,
                        'transition_sequence' => $sequence,
                        'amount' => $allowance,
                        'granted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('ai_credit_ledger_entries')->insert([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => (string) $tenant->id,
                        'entry_type' => 'manual_adjustment',
                        'balance_delta' => $allowance - $previousBalance,
                        'reserved_delta' => -$previousReserved,
                        'reservation_id' => null,
                        'billing_period' => $period,
                        'idempotency_key' => "commercial-credit-cutover:{$pricingVersion}",
                        'metadata' => json_encode([
                            'reason' => 'legacy_provider_usd_to_commercial_credits',
                            'pricing_version' => $pricingVersion,
                            'plan' => $plan,
                            'allowance' => $allowance,
                            'previous_balance' => $previousBalance,
                            'previous_reserved_balance' => $previousReserved,
                            'grant_id' => $grantId,
                            'transition_sequence' => $sequence,
                        ], JSON_THROW_ON_ERROR),
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    /**
     * This cutover is intentionally forward-only. Application rollback must keep
     * this schema and deploy compatible code; silently removing unit markers
     * would let legacy code reinterpret commercial credits as provider USD.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Commercial AI credit cutover is forward-only; restore a pre-cutover database snapshot instead.',
        );
    }

    private function effectivePlan(object $tenant, Carbon $now): string
    {
        $rawPlan = strtolower(trim((string) ($tenant->plan_type ?? 'semilla')));
        $aliases = (array) config('subscriptions.legacy_plan_aliases', []);
        $plan = (string) ($aliases[$rawPlan] ?? $rawPlan);

        if (! in_array($plan, ['pro', 'enterprise'], true)) {
            return 'semilla';
        }

        if (! in_array((string) ($tenant->subscription_status ?? ''), ['active', 'trial'], true)) {
            return 'semilla';
        }

        $endsAt = $tenant->subscription_ends_at;
        if ($endsAt !== null && $now->greaterThanOrEqualTo(Carbon::parse((string) $endsAt)->utc())) {
            return 'semilla';
        }

        return $plan;
    }
};
