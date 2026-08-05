<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Analytics\TrustedCommercialOutcomeProjector;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CommercialOutcomeReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_detects_and_repair_restores_a_missing_projection_without_mutating_source(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create(['total' => '12.34']);
        DB::table('orders')->where('id', $order->getKey())->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $before = DB::table('orders')->where('id', $order->getKey())->first();

        $this->artisan('analytics:reconcile-commercial-outcomes', [
            '--tenant' => (string) $tenant->getKey(),
            '--dry-run' => true,
        ])->expectsOutputToContain('"missing":1')
            ->assertSuccessful();
        $this->assertDatabaseCount('verified_commercial_outcomes', 0);

        $this->artisan('analytics:reconcile-commercial-outcomes', [
            '--tenant' => (string) $tenant->getKey(),
        ])->expectsOutputToContain('"missing":1')
            ->assertSuccessful();

        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'amount_minor' => 1234,
        ]);
        self::assertEquals(
            $before,
            DB::table('orders')->where('id', $order->getKey())->first(),
        );
    }

    public function test_reconciliation_detects_and_repairs_amount_currency_and_timestamp_drift(): void
    {
        $tenant = Tenant::factory()->create();
        $paidAt = now()->subHour()->startOfSecond();
        $order = Order::factory()->forTenant($tenant)->create([
            'total' => '25.50',
            'currency' => 'USD',
        ]);
        DB::table('orders')->where('id', $order->getKey())->update([
            'payment_status' => 'paid',
            'paid_at' => $paidAt,
        ]);
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenant->getKey(),
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'projection_key' => "{$tenant->getKey()}:order:{$order->getKey()}:".CommercialOutcome::OrderPaid->value,
            'amount_minor' => 1,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('analytics:reconcile-commercial-outcomes', [
            '--tenant' => (string) $tenant->getKey(),
            '--dry-run' => true,
        ])->expectsOutputToContain('"drifted":1')
            ->assertSuccessful();

        $this->artisan('analytics:reconcile-commercial-outcomes', [
            '--tenant' => (string) $tenant->getKey(),
        ])->expectsOutputToContain('"repaired":1')
            ->assertSuccessful();

        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'amount_minor' => 2550,
            'currency' => 'USD',
            'source_occurred_at' => $paidAt,
        ]);
    }

    public function test_reconciliation_preserves_historical_appointment_terminal_timestamp_after_stale_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $appointment = Appointment::factory()->createQuietly([
            'tenant_id' => $tenant->getKey(),
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        app(TrustedCommercialOutcomeProjector::class)->projectAppointment(
            (string) $tenant->getKey(),
            (int) $appointment->getKey(),
        );
        $occurredAt = DB::table('verified_commercial_outcomes')
            ->where('aggregate_type', 'appointment')
            ->where('aggregate_id', (string) $appointment->getKey())
            ->value('source_occurred_at');

        DB::table('appointments')->where('id', $appointment->getKey())->update([
            'status' => Appointment::STATUS_CANCELLED,
            'updated_at' => now()->addHour(),
        ]);

        $this->artisan('analytics:reconcile-commercial-outcomes', [
            '--tenant' => (string) $tenant->getKey(),
            '--dry-run' => true,
        ])->expectsOutputToContain('"drifted":0')
            ->assertSuccessful();
        self::assertSame($occurredAt, DB::table('verified_commercial_outcomes')
            ->where('aggregate_type', 'appointment')
            ->where('aggregate_id', (string) $appointment->getKey())
            ->value('source_occurred_at'));
    }
}
