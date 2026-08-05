<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Payments\Actions\RefundOrderPaymentAction;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class TrustedOrderRefundFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_confirmed_refund_updates_order_and_verified_net_revenue_idempotently(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create([
            'total' => '31.25',
            'currency' => 'HNL',
            'payment_status' => 'paid',
            'payment_transaction_id' => 'charge_verified_1',
            'paid_at' => now()->subMinute(),
        ]);

        $action = app(RefundOrderPaymentAction::class);
        $refundId = $action->execute((string) $tenant->getKey(), (int) $order->getKey());
        $repeatId = $action->execute((string) $tenant->getKey(), (int) $order->getKey());

        self::assertSame($refundId, $repeatId);
        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'tenant_id' => $tenant->getKey(),
            'payment_status' => 'refunded',
            'refund_transaction_id' => $refundId,
        ]);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderPaid->value,
            'amount_minor' => 3125,
        ]);
        $this->assertDatabaseHas('verified_commercial_outcomes', [
            'tenant_id' => $tenant->getKey(),
            'aggregate_id' => (string) $order->getKey(),
            'outcome' => CommercialOutcome::OrderRefunded->value,
            'amount_minor' => -3125,
        ]);
        self::assertSame(2, \DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenant->getKey())
            ->where('aggregate_id', (string) $order->getKey())
            ->whereIn('outcome', [
                CommercialOutcome::OrderPaid->value,
                CommercialOutcome::OrderRefunded->value,
            ])->count());
    }

    public function test_refund_action_rejects_cross_tenant_or_unconfirmed_orders(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create([
            'payment_status' => 'pending',
            'payment_transaction_id' => null,
        ]);

        $this->expectException(LogicException::class);
        app(RefundOrderPaymentAction::class)->execute(
            (string) $other->getKey(),
            (int) $order->getKey(),
        );
    }

    public function test_pending_refund_attempt_resumes_with_the_same_durable_idempotency_key(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create([
            'total' => '8.00',
            'payment_status' => 'refund_pending',
            'payment_transaction_id' => 'charge_resume_1',
            'refund_idempotency_key' => 'stable-refund-key-1',
            'refund_attempted_at' => now()->subMinute(),
            'paid_at' => now()->subHour(),
        ]);

        $refundId = app(RefundOrderPaymentAction::class)->execute(
            (string) $tenant->getKey(),
            (int) $order->getKey(),
        );

        self::assertSame('refund_'.hash('sha256', 'stable-refund-key-1'), $refundId);
        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'payment_status' => 'refunded',
            'refund_idempotency_key' => 'stable-refund-key-1',
            'refund_transaction_id' => $refundId,
        ]);
    }
}
