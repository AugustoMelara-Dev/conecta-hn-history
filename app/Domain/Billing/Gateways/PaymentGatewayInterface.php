<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\DTOs\PaymentIntentResult;

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
     * Create a provider checkout/payment intent without confirming it.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        string $idempotencyKey,
        array $metadata = []
    ): PaymentIntentResult;

    /**
     * Charge a payment method.
     *
     * @param  int  $amountInCents  Amount in smallest currency unit
     * @param  string  $currency  ISO currency code (e.g., 'USD', 'HNL')
     * @param  string  $paymentMethodId  Provider-specific payment method identifier
     * @param  array<string, mixed>  $metadata  Additional transaction metadata
     *
     * @throws \App\Domain\Billing\Exceptions\PaymentDeclinedException
     * @throws \App\Domain\Billing\Exceptions\PaymentGatewayUnavailableException
     * @throws \App\Domain\Billing\Exceptions\UnknownPaymentException
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
    public function refund(string $transactionId, ?int $amountInCents = null): ChargeResult;
}
