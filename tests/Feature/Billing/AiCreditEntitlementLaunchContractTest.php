<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Billing\Enums\AiCreditEntryType;
use App\Domain\Billing\Services\AiCreditPricingService;
use App\Domain\Billing\Services\AiCreditUsageService;
use App\Domain\Billing\Services\MonthlyAiCreditGrantService;
use App\Domain\Billing\Services\PlanEntitlementService;
use App\Domain\Identity\Events\TenantRegistered;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Infrastructure\TenantGovernance\Capabilities\ConfigCapabilityManager;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AiCreditUsageThresholdNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AiCreditEntitlementLaunchContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_snapshot_normalizes_legacy_names_and_downgrades_expired_access(): void
    {
        $plans = $this->app->make(PlanEntitlementService::class);

        self::assertSame(TenantPlan::SEMILLA, $plans->normalizePlan('free'));
        self::assertSame(10, $plans->forPlan('semilla')->limit('products'));
        self::assertTrue($plans->forPlan('pro')->includes(Capability::AI_CONTENT));
        self::assertSame(15.0, $plans->forPlan('pro')->limit('ai_monthly_credits'));

        $activePro = Tenant::factory()->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
            'product_limit' => 77,
        ]);
        $expiredPro = Tenant::factory()->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->subMinute(),
        ]);

        self::assertSame(TenantPlan::PRO, $plans->forTenant($activePro)->plan);
        self::assertSame(100, $plans->forTenant($activePro)->limit('products'));
        self::assertSame(TenantPlan::SEMILLA, $plans->forTenant($expiredPro)->plan);
    }

    public function test_monthly_grant_is_idempotent_resets_without_carry_over_and_is_tenant_scoped(): void
    {
        $tenantA = Tenant::factory()->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 9.0,
            'ai_reserved_balance' => 0.0,
        ]);
        $tenantB = Tenant::factory()->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'ai_balance' => 2.0,
            'ai_reserved_balance' => 0.0,
        ]);
        $grants = $this->app->make(MonthlyAiCreditGrantService::class);
        $period = Carbon::parse('2026-07-01', 'UTC');

        self::assertTrue($grants->grant($tenantA, $period));
        self::assertFalse($grants->grant($tenantA, $period));
        self::assertTrue($grants->grant($tenantB, $period));

        self::assertSame(1.0, (float) $tenantA->refresh()->ai_balance);
        self::assertSame(15.0, (float) $tenantB->refresh()->ai_balance);
        self::assertSame(2, DB::table('ai_credit_grants')->count());
        self::assertSame(1, DB::table('ai_credit_grants')->where('tenant_id', $tenantA->id)->count());
        self::assertSame(1, DB::table('ai_credit_ledger_entries')
            ->where('tenant_id', $tenantA->id)
            ->where('entry_type', AiCreditEntryType::MONTHLY_GRANT->value)
            ->count());

        $tenantA->forceFill([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ])->save();

        self::assertTrue($grants->grant($tenantA, $period));
        self::assertFalse($grants->grant($tenantA, $period));
        self::assertSame(15.0, (float) $tenantA->refresh()->ai_balance);
        self::assertSame(2, DB::table('ai_credit_grants')->where('tenant_id', $tenantA->id)->count());

        self::assertTrue($grants->grant($tenantA, $period->copy()->addMonth()));
        self::assertSame(3, DB::table('ai_credit_grants')->where('tenant_id', $tenantA->id)->count());
    }

    public function test_tenant_registered_event_grants_initial_credits_once(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 0,
            'ai_reserved_balance' => 0,
        ]);

        event(new TenantRegistered(
            tenantId: (string) $tenant->id,
            userId: (string) $tenant->user_id,
        ));
        event(new TenantRegistered(
            tenantId: (string) $tenant->id,
            userId: (string) $tenant->user_id,
        ));

        self::assertSame(1.0, (float) $tenant->refresh()->ai_balance);
        self::assertSame(1, DB::table('ai_credit_grants')->where('tenant_id', $tenant->id)->count());
    }

    public function test_reserve_settle_and_refund_append_exactly_once_without_cross_tenant_entries(): void
    {
        $tenantA = Tenant::factory()->create(['ai_balance' => 1.0, 'ai_reserved_balance' => 0.0]);
        $tenantB = Tenant::factory()->create(['ai_balance' => 1.0, 'ai_reserved_balance' => 0.0]);
        $budget = $this->app->make(BudgetEnforcer::class);

        $settled = $budget->reserve((string) $tenantA->id, 0.4, 'private_chat');
        $budget->settle((string) $tenantA->id, 0.4, 0.25, $settled);
        $budget->settle((string) $tenantA->id, 0.4, 0.25, $settled);

        $refunded = $budget->reserve((string) $tenantB->id, 0.3, 'catalog_photo');
        $budget->refund((string) $tenantB->id, 0.3, $refunded, 'provider_unavailable');
        $budget->refund((string) $tenantB->id, 0.3, $refunded, 'provider_unavailable');

        self::assertSame(2, DB::table('ai_credit_ledger_entries')->where('tenant_id', $tenantA->id)->count());
        self::assertSame(2, DB::table('ai_credit_ledger_entries')->where('tenant_id', $tenantB->id)->count());
        self::assertSame(1, DB::table('ai_credit_ledger_entries')
            ->where('tenant_id', $tenantA->id)
            ->where('entry_type', AiCreditEntryType::RESERVATION_SETTLED->value)
            ->count());
        self::assertSame(1, DB::table('ai_credit_ledger_entries')
            ->where('tenant_id', $tenantB->id)
            ->where('entry_type', AiCreditEntryType::RESERVATION_REFUNDED->value)
            ->count());
        self::assertSame(0.75, (float) $tenantA->refresh()->ai_balance);
        self::assertSame(1.0, (float) $tenantB->refresh()->ai_balance);
    }

    public function test_daily_cap_blocks_before_reservation_and_usage_emits_text_thresholds(): void
    {
        config()->set('subscriptions.plans.semilla.limits.ai_daily_credits', 0.5);
        $tenant = Tenant::factory()->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0.0,
        ]);
        $budget = $this->app->make(BudgetEnforcer::class);

        $reservation = $budget->reserve((string) $tenant->id, 0.4, 'first');
        $budget->settle((string) $tenant->id, 0.4, 0.4, $reservation);

        try {
            $budget->reserve((string) $tenant->id, 0.11, 'over_daily_cap');
            self::fail('The daily AI cap must reject before creating a reservation.');
        } catch (InsufficientAiCreditsException $exception) {
            self::assertSame('daily_limit', $exception->context()['reason']);
        }

        self::assertSame(1, DB::table('ai_credit_reservations')->where('tenant_id', $tenant->id)->count());

        $usage = $this->app->make(AiCreditUsageService::class)->forTenant($tenant);
        self::assertSame(40, $usage['percentage']);
        self::assertSame('healthy', $usage['status']);

        DB::table('ai_credit_reservations')->where('id', $reservation)->update(['actual_cost' => 0.91]);
        $usage = $this->app->make(AiCreditUsageService::class)->forTenant($tenant);
        self::assertSame(91, $usage['percentage']);
        self::assertSame(90, $usage['threshold']);
        self::assertSame('critical', $usage['status']);
    }

    public function test_crossed_usage_thresholds_are_recorded_once_and_notify_without_provider_details(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 1.0,
            'ai_reserved_balance' => 0.0,
        ]);
        $budget = $this->app->make(BudgetEnforcer::class);

        $reservation = $budget->reserve((string) $tenant->id, 1.0, 'monthly_threshold');
        $budget->settle((string) $tenant->id, 1.0, 1.0, $reservation);
        $budget->settle((string) $tenant->id, 1.0, 1.0, $reservation);

        self::assertSame([70, 90, 100], DB::table('ai_credit_usage_alerts')
            ->where('tenant_id', $tenant->id)
            ->orderBy('threshold')
            ->pluck('threshold')
            ->map(static fn ($value): int => (int) $value)
            ->all());

        Notification::assertSentTo(
            $owner,
            AiCreditUsageThresholdNotification::class,
            static function (AiCreditUsageThresholdNotification $notification) use ($owner): bool {
                $payload = $notification->toArray($owner);

                return $payload['threshold'] === 100
                    && $payload['percentage'] === 100
                    && str_ends_with((string) $payload['url'], '/manage-billing')
                    && ! str_contains(strtolower(json_encode($payload, JSON_THROW_ON_ERROR)), 'token');
            },
        );
    }

    public function test_grant_command_validates_period_and_cannot_double_credit(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'ai_balance' => 0,
            'ai_reserved_balance' => 0,
        ]);

        self::assertSame(2, Artisan::call('ai:grant-monthly-credits', ['--period' => 'July-2026']));
        self::assertSame(0, Artisan::call('ai:grant-monthly-credits', [
            '--period' => '2026-07',
            '--tenant' => (string) $tenant->id,
        ]));
        self::assertSame(0, Artisan::call('ai:grant-monthly-credits', [
            '--period' => '2026-07',
            '--tenant' => (string) $tenant->id,
        ]));

        self::assertSame(1.0, (float) $tenant->refresh()->ai_balance);
        self::assertSame(1, DB::table('ai_credit_grants')->where('tenant_id', $tenant->id)->count());
    }

    public function test_commercial_credit_schema_keeps_denomination_and_pricing_snapshot(): void
    {
        self::assertTrue(Schema::hasColumns('ai_credit_reservations', [
            'denomination',
            'pricing_version',
            'credits_per_provider_usd',
            'minimum_charge',
        ]));
        self::assertTrue(Schema::hasColumns('ai_executions', [
            'charged_credits',
            'credit_pricing_version',
            'credits_per_provider_usd',
            'credit_minimum_charge',
        ]));
        self::assertTrue(Schema::hasColumn('tenants', 'ai_credit_unit_version'));
    }

    public function test_legacy_credit_unit_cutover_refunds_pending_rows_and_resets_entitlement(): void
    {
        $migration = require database_path('migrations/2026_07_24_000001_version_commercial_ai_credit_units.php');
        Schema::table('tenants', function ($table): void {
            $table->dropColumn('ai_credit_unit_version');
        });
        Schema::table('ai_executions', function ($table): void {
            $table->dropColumn(['charged_credits', 'credit_pricing_version', 'credits_per_provider_usd', 'credit_minimum_charge']);
        });
        Schema::table('ai_credit_reservations', function ($table): void {
            $table->dropIndex('ai_credit_reservations_unit_usage_idx');
            $table->dropColumn(['denomination', 'pricing_version', 'credits_per_provider_usd', 'minimum_charge']);
        });

        $tenant = Tenant::factory()->create([
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
            'ai_balance' => 0.50,
            'ai_reserved_balance' => 0.20,
        ]);
        DB::table('ai_credit_reservations')->insert([
            'id' => str_repeat('a', 32),
            'tenant_id' => (string) $tenant->getKey(),
            'operation' => 'legacy.operation',
            'estimated_cost' => 0.20,
            'actual_cost' => null,
            'status' => 'pending',
            'usage_metadata' => null,
            'failure_reason' => null,
            'settled_at' => null,
            'refunded_at' => null,
            'created_at' => now()->utc()->subHour(),
            'updated_at' => now()->utc()->subHour(),
        ]);

        $migration->up();

        $tenant->refresh();
        self::assertSame(15.0, (float) $tenant->ai_balance);
        self::assertSame(0.0, (float) $tenant->ai_reserved_balance);
        self::assertSame('credits-v1', (string) $tenant->ai_credit_unit_version);
        self::assertDatabaseHas('ai_credit_reservations', [
            'id' => str_repeat('a', 32),
            'status' => 'refunded',
            'denomination' => 'legacy_provider_usd',
            'failure_reason' => 'legacy_credit_unit_cutover',
        ]);
        self::assertDatabaseHas('ai_credit_ledger_entries', [
            'tenant_id' => (string) $tenant->getKey(),
            'entry_type' => 'manual_adjustment',
            'idempotency_key' => 'commercial-credit-cutover:credits-v1',
        ]);
    }

    public function test_commercial_credit_cutover_refuses_unsafe_schema_rollback(): void
    {
        $migration = require database_path('migrations/2026_07_24_000001_version_commercial_ai_credit_units.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('forward-only');
        $migration->down();
    }

    public function test_commercial_credit_pricing_is_versioned_and_not_raw_provider_usd(): void
    {
        config()->set([
            'ai.commercial_credits.credits_per_provider_usd' => 20.0,
            'ai.commercial_credits.minimum_charge' => 0.01,
            'ai.commercial_credits.pricing_version' => 'credits-v1-test',
        ]);
        $pricing = new AiCreditPricingService;
        self::assertSame(1.0, $pricing->fromProviderUsd(0.05));
        self::assertNotSame(0.05, $pricing->fromProviderUsd(0.05));
        self::assertSame('credits-v1-test', $pricing->version());
    }

    public function test_settlement_overrun_never_diverges_ledger_or_exceeds_authorized_credits(): void
    {
        $tenant = Tenant::factory()->create(['plan_type' => 'semilla', 'subscription_status' => 'active', 'ai_balance' => 1.0, 'ai_reserved_balance' => 0.0]);
        $budget = $this->app->make(BudgetEnforcer::class);
        $reservation = $budget->reserve((string) $tenant->id, 0.5, 'overrun_guard');
        $budget->settle((string) $tenant->id, 0.5, 0.9, $reservation);
        self::assertSame(0.5, (float) $tenant->refresh()->ai_balance);
        self::assertSame(0.5, (float) DB::table('ai_credit_reservations')->where('id', $reservation)->value('actual_cost'));
        self::assertSame(-0.5, (float) DB::table('ai_credit_ledger_entries')->where('reservation_id', $reservation)->where('entry_type', AiCreditEntryType::RESERVATION_SETTLED->value)->value('balance_delta'));
    }

    public function test_same_month_plan_return_resets_entitlement_and_monthly_limit_blocks_pro_residue(): void
    {
        $tenant = Tenant::factory()->create(['plan_type' => 'semilla', 'subscription_status' => 'active', 'ai_balance' => 0.0, 'ai_reserved_balance' => 0.0]);
        $grants = $this->app->make(MonthlyAiCreditGrantService::class);
        self::assertTrue($grants->grant($tenant));
        $tenant->forceFill(['plan_type' => 'pro', 'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth()])->save();
        self::assertTrue($grants->grant($tenant->refresh()));
        self::assertSame(15.0, (float) $tenant->refresh()->ai_balance);
        $tenant->forceFill(['plan_type' => 'semilla', 'subscription_status' => 'expired', 'subscription_ends_at' => now()->subMinute()])->save();
        self::assertTrue($grants->grant($tenant->refresh()));
        self::assertSame(1.0, (float) $tenant->refresh()->ai_balance);
        self::assertSame(3, DB::table('ai_credit_grants')->where('tenant_id', $tenant->id)->count());
        $this->expectException(InsufficientAiCreditsException::class);
        $this->app->make(BudgetEnforcer::class)->reserve((string) $tenant->id, 1.01, 'semilla_monthly_guard');
    }

    public function test_daily_accounting_uses_utc_at_the_timezone_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 01:30:00', 'UTC'));
        try {
            $tenant = Tenant::factory()->create(['plan_type' => 'semilla', 'subscription_status' => 'active', 'ai_balance' => 1.0, 'ai_reserved_balance' => 0.0]);
            $budget = $this->app->make(BudgetEnforcer::class);
            $reservation = $budget->reserve((string) $tenant->id, 0.4, 'utc_boundary');
            $budget->settle((string) $tenant->id, 0.4, 0.4, $reservation);
            $usage = $this->app->make(AiCreditUsageService::class)->forTenant($tenant->refresh());
            self::assertSame(0.4, $usage['spent_today']);
            self::assertStringStartsWith('2026-07-23 01:30:00', (string) DB::table('ai_credit_reservations')->where('id', $reservation)->value('settled_at'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_semilla_service_business_keeps_billing_portal_entitlement(): void
    {
        $tenant = Tenant::factory()->create(['plan_type' => 'semilla', 'subscription_status' => 'active', 'commercial_mode' => 'appointments']);
        $capabilities = (new ConfigCapabilityManager)->allForTenant($tenant);
        self::assertContains(Capability::BILLING_PORTAL, $capabilities);
        self::assertContains(Capability::CATALOG_BASIC, $capabilities);
    }

    public function test_billing_surface_explains_limits_commission_thresholds_and_failure_refunds(): void
    {
        $view = file_get_contents(resource_path('views/filament/app/pages/manage-billing.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString('data-testid="billing-entitlements"', $view);
        self::assertStringContainsString('data-testid="billing-ai-usage"', $view);
        self::assertStringContainsString('role="progressbar"', $view);
        self::assertStringContainsString('70% consumido', $view);
        self::assertStringContainsString('90% consumido', $view);
        self::assertStringContainsString('Limite mensual alcanzado', $view);
        self::assertStringContainsString('0% de comision sobre tus ventas', $view);
        self::assertStringContainsString('Una solicitud fallida no descuenta saldo', $view);
        self::assertStringContainsString('data-testid="billing-renewal-form"', $view);
        self::assertStringContainsString('{{ $this->form }}', $view);
        self::assertStringContainsString('@if($daysRemaining <= 7)', $view);
        self::assertStringNotContainsString('$daysRemaining <= 7 && $daysRemaining > 0', $view);
        self::assertStringNotContainsString('token', strtolower($view));

        $page = file_get_contents(app_path('Filament/App/Pages/ManageBilling.php'));
        self::assertIsString($page);
        self::assertStringContainsString('$this->stripeSubscriptionIdFor($tenant) !== null', $page);
        self::assertStringContainsString("? 'openCustomerPortal'", $page);
        self::assertStringContainsString('sin crear un cobro recurrente duplicado', $page);
        self::assertStringContainsString("'-pro-'.now()->utc()->format('Y-m')", $page);
        self::assertStringNotContainsString('Str::uuid()', $page);
    }
}
