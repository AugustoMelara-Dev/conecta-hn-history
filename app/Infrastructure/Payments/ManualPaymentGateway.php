<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\DTOs\PaymentIntentResult;
use App\Domain\Billing\Gateways\PaymentGatewayInterface;

final class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        string $idempotencyKey,
        array $metadata = []
    ): PaymentIntentResult {
        return PaymentIntentResult::failure('Manual billing does not create provider payment intents.');
    }

    public function charge(
        int $amountInCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = []
    ): ChargeResult {
        return ChargeResult::failure('Manual billing does not charge payment methods automatically.');
    }

    public function refund(string $transactionId, ?int $amountInCents = null): ChargeResult
    {
        return ChargeResult::failure('Manual billing refunds must be processed outside the gateway.');
    }
}
