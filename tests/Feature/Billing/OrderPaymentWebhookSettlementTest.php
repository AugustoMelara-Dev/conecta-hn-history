<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderPaymentWebhookSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_order_payment_is_settled_once_against_local_checkout_snapshot(): void
    {
        config()->set('services.payment.webhook_secret', 'order-webhook-secret');
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create([
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'total' => '10.50',
            'currency' => 'HNL',
            'idempotency_key' => 'checkout-order-1',
        ]);
        $payload = $this->payload($tenant, $order, 'evt_order_paid', 1050);

        $this->sendSigned($payload)->assertOk();
        $this->sendSigned($payload)->assertAccepted();

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'tenant_id' => $tenant->getKey(),
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'payment_transaction_id' => 'pi_order_paid',
        ]);
    }

    public function test_late_payment_for_cancelled_order_is_durably_refunded(): void
    {
        config()->set('services.payment.webhook_secret', 'order-webhook-secret');
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create([
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => 'pending',
            'total' => '8.00',
            'currency' => 'HNL',
            'idempotency_key' => 'checkout-order-cancelled',
        ]);

        $this->sendSigned($this->payload($tenant, $order, 'evt_order_late', 800))->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => 'refunded',
            'payment_transaction_id' => 'pi_order_paid',
        ]);
    }

    private function payload(Tenant $tenant, Order $order, string $eventId, int $amount): array
    {
        return [
            'id' => $eventId,
            'event' => 'payment.success',
            'meta' => ['tenant_id' => (string) $tenant->getKey(), 'provider' => 'test_gateway'],
            'data' => ['object' => [
                'id' => 'pi_order_paid',
                'amount' => $amount,
                'currency' => 'HNL',
                'metadata' => [
                    'tenant_id' => (string) $tenant->getKey(),
                    'order_id' => (int) $order->getKey(),
                    'order_idempotency_key' => (string) $order->idempotency_key,
                ],
            ]],
        ];
    }

    private function sendSigned(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'order-webhook-secret');

        return $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Signature' => $signature,
            'HTTP_X-Timestamp' => (string) $timestamp,
        ], $body);
    }
}
