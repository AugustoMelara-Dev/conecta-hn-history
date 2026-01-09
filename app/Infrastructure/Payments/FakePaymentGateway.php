<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Domain\Payments\DTOs\ChargeResult;
use App\Domain\Payments\Gateways\PaymentGatewayInterface;

/**
 * FakePaymentGateway - Test Double for Payment Processing
 *
 * Used in testing environment to simulate payment operations
 * without making real API calls.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var array<string, ChargeResult> */
    private array $chargeResults = [];

    /**
     * Pre-configure a charge result for testing.
     */
    public function willSucceed(string $transactionId = 'fake_txn_123'): void
    {
        $this->chargeResults['next'] = ChargeResult::success($transactionId);
    }

    /**
     * Pre-configure a failure result for testing.
     */
    public function willFail(string $errorMessage = 'Test failure'): void
    {
        $this->chargeResults['next'] = ChargeResult::failure($errorMessage);
    }

    /**
     * @inheritDoc
     */
    public function charge(
        int $amountInCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = []
    ): ChargeResult {
        if (isset($this->chargeResults['next'])) {
            $result = $this->chargeResults['next'];
            unset($this->chargeResults['next']);
            return $result;
        }

        // Default behavior: succeed
        return ChargeResult::success(
            transactionId: 'fake_' . uniqid(),
            receiptUrl: 'https://fake-receipts.example.com/' . uniqid(),
        );
    }

    /**
     * @inheritDoc
     */
    public function refund(string $transactionId, ?int $amountInCents = null): ChargeResult
    {
        return ChargeResult::success(
            transactionId: 'refund_' . uniqid(),
        );
    }
}
