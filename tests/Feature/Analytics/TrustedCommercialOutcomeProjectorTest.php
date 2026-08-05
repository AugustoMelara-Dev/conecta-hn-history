<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Analytics\TrustedCommercialOutcomeProjector;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class TrustedCommercialOutcomeProjectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_projection_derives_exact_server_value_and_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create(['total' => '42.37']);
        DB::table('orders')->where('id', $order->getKey())->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $projector = app(TrustedCommercialOutcomeProjector::class);
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());

        $this->assertDatabaseCount('verified_commercial_outcomes', 1);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'amount_minor' => 4237,
            'currency' => 'HNL',
        ]);
    }

    public function test_refund_delivery_and_cancellation_have_non_overlapping_amount_semantics(): void
    {
        $tenant = Tenant::factory()->create();
        $refunded = Order::factory()->forTenant($tenant)->create(['total' => '10.25']);
        DB::table('orders')->where('id', $refunded->getKey())->update([
            'status' => Order::STATUS_DELIVERED,
            'payment_status' => 'refunded',
            'paid_at' => now()->subDay(),
            'refunded_at' => now(),
            'delivered_at' => now()->subHours(12),
        ]);

        $cancelled = Order::factory()->forTenant($tenant)->create(['total' => '7.00']);
        DB::table('orders')->where('id', $cancelled->getKey())->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $projector = app(TrustedCommercialOutcomeProjector::class);
        $projector->projectOrder((string) $tenant->getKey(), (int) $refunded->getKey());
        $projector->projectOrder((string) $tenant->getKey(), (int) $cancelled->getKey());

        self::assertSame(0, (int) DB::table('verified_commercial_outcomes')
            ->where('aggregate_id', (string) $refunded->getKey())
            ->sum('amount_minor'));
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'aggregate_id' => (string) $refunded->getKey(),
            'outcome' => CommercialOutcome::OrderDelivered->value,
            'amount_minor' => 0,
        ]);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'aggregate_id' => (string) $cancelled->getKey(),
            'outcome' => CommercialOutcome::OrderCancelled->value,
            'amount_minor' => 0,
        ]);
    }

    public function test_terminal_refund_projection_is_not_removed_by_an_out_of_order_paid_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create(['total' => '5.00']);
        DB::table('orders')->where('id', $order->getKey())->update([
            'payment_status' => 'refunded',
            'paid_at' => now()->subHour(),
            'refunded_at' => now(),
        ]);

        $projector = app(TrustedCommercialOutcomeProjector::class);
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());
        DB::table('orders')->where('id', $order->getKey())->update([
            'payment_status' => 'paid',
            'refunded_at' => null,
            'total' => '999.99',
            'currency' => 'USD',
        ]);
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());

        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderRefunded->value,
            'amount_minor' => -500,
            'currency' => 'HNL',
        ]);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'amount_minor' => 500,
            'currency' => 'HNL',
        ]);
        self::assertSame(0, (int) DB::table('verified_commercial_outcomes')
            ->where('aggregate_id', (string) $order->getKey())
            ->sum('amount_minor'));
        $this->assertDatabaseCount('verified_commercial_outcomes', 2);
    }

    public function test_out_of_order_order_status_cannot_replace_a_projected_terminal_outcome(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create();
        DB::table('orders')->where('id', $order->getKey())->update([
            'status' => Order::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        $projector = app(TrustedCommercialOutcomeProjector::class);
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());
        DB::table('orders')->where('id', $order->getKey())->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now()->addMinute(),
        ]);
        $projector->projectOrder((string) $tenant->getKey(), (int) $order->getKey());

        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderDelivered->value,
        ]);
        $this->assertDatabaseMissing('verified_commercial_outcomes', [
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderCancelled->value,
        ]);
    }

    public function test_appointment_outcomes_are_distinct_and_observed(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);
        /** @var Appointment $appointment */
        $appointment = Appointment::factory()->createQuietly([
            'tenant_id' => $tenant->getKey(),
            'status' => Appointment::STATUS_PENDING,
        ]);

        $writer = app(CanonicalReservationWriter::class);
        $writer->transition(new TransitionReservationCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $owner->getKey(),
            appointmentId: (int) $appointment->getKey(),
            targetStatus: Appointment::STATUS_CONFIRMED,
        ));
        $writer->transition(new TransitionReservationCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $owner->getKey(),
            appointmentId: (int) $appointment->getKey(),
            targetStatus: Appointment::STATUS_COMPLETED,
        ));

        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_type' => 'appointment',
            'aggregate_id' => (string) $appointment->getKey(),
            'outcome' => CommercialOutcome::AppointmentConfirmed->value,
        ]);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_type' => 'appointment',
            'aggregate_id' => (string) $appointment->getKey(),
            'outcome' => CommercialOutcome::AppointmentCompleted->value,
        ]);
    }

    public function test_cross_tenant_aggregate_reference_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create();

        $this->expectException(LogicException::class);
        app(TrustedCommercialOutcomeProjector::class)
            ->projectOrder((string) $other->getKey(), (int) $order->getKey());
    }
}
