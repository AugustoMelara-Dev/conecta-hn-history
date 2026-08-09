<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Domain\Payments\DTOs\ChargeResult;
use App\Domain\Payments\Exceptions\PaymentDeclinedException;
use App\Domain\Payments\Exceptions\PaymentGatewayUnavailableException;
use App\Domain\Payments\Exceptions\UnknownPaymentException;
use App\Domain\Payments\Gateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

/**
 * StripePaymentGateway - Infrastructure Adapter for Stripe Payments
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ANTI-CORRUPTION LAYER: NO Stripe exceptions escape this class.         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  ALL Stripe-specific exceptions are caught and translated to Domain     ║
 * ║  exceptions. The Application layer NEVER knows about Stripe.            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    private bool $configured;

    public function __construct()
    {
        $secret = trim((string) config('services.stripe.secret', ''));
        $this->configured = $secret !== '';
        if ($this->configured) {
            Stripe::setApiKey($secret);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function charge(
        int $amountInCents,
        string $currency,
        string $paymentMethodId,
        array $metadata = []
    ): ChargeResult {
        $this->assertConfigured();

        try {
            $intent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'metadata' => $metadata,
            ]);

            // Check if payment requires additional action (3D Secure, etc.)
            if ($intent->status === 'requires_action') {
                Log::info('Payment requires additional authentication', [
                    'intent_id' => $intent->id,
                ]);

                return ChargeResult::failure('Payment requires additional authentication');
            }

            if ($intent->status !== 'succeeded') {
                return ChargeResult::failure('Payment was not settled by the provider.');
            }

            return ChargeResult::success(
                transactionId: $intent->id,
                receiptUrl: $intent->charges?->data[0]?->receipt_url,
            );

        } catch (CardException $e) {
            // Card was declined
            Log::warning('Payment declined', [
                'decline_code' => $e->getDeclineCode(),
                'exception' => $e::class,
            ]);

            throw new PaymentDeclinedException(
                declineCode: $e->getDeclineCode() ?? 'unknown',
                message: 'Payment was declined by the provider.',
                previous: $e
            );

        } catch (RateLimitException $e) {
            // Too many requests to Stripe
            Log::error('Stripe rate limit exceeded', [
                'exception' => $e::class,
            ]);

            throw new PaymentGatewayUnavailableException(
                message: 'Payment service is temporarily overloaded. Please try again.',
                previous: $e
            );

        } catch (InvalidRequestException $e) {
            // Invalid parameters sent to Stripe
            Log::error('Invalid Stripe request', [
                'exception' => $e::class,
                'param' => $e->getStripeParam(),
            ]);

            throw new UnknownPaymentException(
                message: 'Invalid payment request configuration',
                previous: $e
            );

        } catch (AuthenticationException $e) {
            // API key issues
            Log::critical('Stripe authentication failed - check API keys', [
                'exception' => $e::class,
            ]);

            throw new PaymentGatewayUnavailableException(
                message: 'Payment service configuration error',
                previous: $e
            );

        } catch (ApiConnectionException $e) {
            // Network issues
            Log::error('Stripe connection failed', [
                'exception' => $e::class,
            ]);

            throw new PaymentGatewayUnavailableException(
                message: 'Unable to connect to payment service',
                previous: $e
            );

        } catch (\Throwable $e) {
            // Catch-all for any unexpected errors
            Log::error('Unexpected payment error', [
                'exception' => $e::class,
            ]);

            throw new UnknownPaymentException(
                message: 'An unexpected error occurred during payment processing',
                previous: $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refund(
        string $transactionId,
        ?int $amountInCents = null,
        ?string $idempotencyKey = null,
    ): ChargeResult {
        $this->assertConfigured();

        try {
            $params = ['payment_intent' => $transactionId];

            if ($amountInCents !== null) {
                $params['amount'] = $amountInCents;
            }

            $options = $idempotencyKey === null ? [] : ['idempotency_key' => $idempotencyKey];
            $refund = Refund::create($params, $options);

            if ($refund->status === 'succeeded') {
                return ChargeResult::success(transactionId: $refund->id);
            }

            return ChargeResult::failure('Refund is pending or unavailable at the provider.');

        } catch (InvalidRequestException $e) {
            Log::error('Invalid refund request', [
                'transaction_id' => $transactionId,
                'exception' => $e::class,
            ]);

            throw new UnknownPaymentException(
                message: 'Unable to process the refund request.',
                previous: $e
            );

        } catch (ApiConnectionException $e) {
            throw new PaymentGatewayUnavailableException(
                message: 'Unable to connect to payment service for refund',
                previous: $e
            );

        } catch (\Throwable $e) {
            Log::error('Unexpected refund error', [
                'exception' => $e::class,
            ]);

            throw new UnknownPaymentException(
                message: 'An unexpected error occurred during refund processing',
                previous: $e
            );
        }
    }

    private function assertConfigured(): void
    {
        if (! $this->configured) {
            throw new PaymentGatewayUnavailableException('Payment provider is not configured.');
        }
    }
}
