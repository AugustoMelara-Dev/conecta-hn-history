<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\DTOs\ChargeResult;
use App\Domain\Payments\Exceptions\PaymentDeclinedException;
use App\Domain\Payments\Exceptions\PaymentGatewayUnavailableException;
use App\Domain\Payments\Exceptions\UnknownPaymentException;

/**
 * PaymentGatewayInterface - Port for Payment Processing
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ABSTRACTION: Domain code NEVER knows about Stripe, PayPal, etc.        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Infrastructure implements this with StripePaymentGateway,               ║
 * ║  PayPalPaymentGateway, or FakePaymentGateway for testing.                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface PaymentGatewayInterface
{
    /**
     * Charge a payment method.
     *
     * @param  int  $amountInCents  Amount in smallest currency unit
     * @param  string  $currency  ISO currency code (e.g., 'USD', 'HNL')
     * @param  string  $paymentMethodId  Provider-specific payment method identifier
     * @param  array<string, mixed>  $metadata  Additional transaction metadata
     *
     * @throws PaymentDeclinedException
     * @throws PaymentGatewayUnavailableException
     * @throws UnknownPaymentException
     */
    public function charge(
        int $amountInCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = []
    ): ChargeResult;

    /**
     * Refund a previous charge.
     *
     * @param  string  $transactionId  The original transaction identifier
     * @param  int|null  $amountInCents  Amount to refund (null = full refund)
     */
    public function refund(
        string $transactionId,
        ?int $amountInCents = null,
        ?string $idempotencyKey = null,
    ): ChargeResult;
}
