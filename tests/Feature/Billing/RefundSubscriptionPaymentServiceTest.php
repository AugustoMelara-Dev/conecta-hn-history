<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Billing\Services\RefundSubscriptionPaymentService;
use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\DTOs\PaymentIntentResult;
use App\Domain\Billing\Gateways\PaymentGatewayInterface;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RefundSubscriptionPaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_refunds_approved_subscription_payment_once(): void
    {
        $tenant = Tenant::factory()->create();
        $payment = SubscriptionPayment::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'amount' => 29.00,
            'bank' => 'stripe',
            'reference_number' => 'pi_refundable',
            'proof_path' => '',
            'status' => SubscriptionPayment::STATUS_APPROVED,
            'idempotency_key' => 'evt-paid',
        ]);

        $gateway = new RefundRecordingGateway;
        $service = new RefundSubscriptionPaymentService($gateway);

        $first = $service->refund((string) $payment->getKey(), 2900, 'customer_request');
        $second = $service->refund((string) $payment->getKey(), 2900, 'customer_request');

        self::assertTrue($first->success);
        self::assertTrue($second->success);
        self::assertSame('re_test_1', $first->transactionId);
        self::assertSame('re_test_1', $second->transactionId);
        self::assertSame(1, $gateway->refundCalls);
        $this->assertDatabaseHas('subscription_payments', [
            'id' => $payment->getKey(),
            'refund_transaction_id' => 're_test_1',
            'refund_reason' => 'customer_request',
        ]);
    }
}

final class RefundRecordingGateway implements PaymentGatewayInterface
{
    public int $refundCalls = 0;

    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        string $idempotencyKey,
        array $metadata = []
    ): PaymentIntentResult {
        return PaymentIntentResult::failure('not used');
    }

    public function charge(
        int $amountInCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = []
    ): ChargeResult {
        return ChargeResult::success('charge-test');
    }

    public function refund(string $transactionId, ?int $amountInCents = null): ChargeResult
    {
        $this->refundCalls++;

        return ChargeResult::success('re_test_'.$this->refundCalls);
    }
}
