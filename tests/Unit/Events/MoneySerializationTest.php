<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Order\OrderPaid;
use App\Events\Payment\PaymentApproved;
use PHPUnit\Framework\TestCase;

final class MoneySerializationTest extends TestCase
{
    public function test_order_paid_serializes_amount_cents_from_money_cents(): void
    {
        $event = OrderPaid::create(
            tenantId: '018f9f38-0000-7000-9000-000000000001',
            orderId: '018f9f38-0000-7000-9000-000000000002',
            orderNumber: 'ORD-100',
            transactionId: 'txn_100',
            paymentMethod: 'card',
            amountCents: 12345,
        );

        self::assertSame(12345, $event->toPrimitives()['amount_cents']);
    }

    public function test_payment_approved_serializes_amount_cents_from_money_cents(): void
    {
        $event = PaymentApproved::create(
            tenantId: '018f9f38-0000-7000-9000-000000000001',
            orderId: 100,
            orderNumber: 'ORD-101',
            transactionId: 'txn_101',
            gatewayName: 'stripe',
            amountCents: 98765,
        );

        self::assertSame(98765, $event->toPrimitives()['amount_cents']);
    }
}
