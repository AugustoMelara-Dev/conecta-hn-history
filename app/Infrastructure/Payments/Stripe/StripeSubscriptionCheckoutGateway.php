<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;
use App\Domain\Billing\Exceptions\PaymentGatewayUnavailableException;
use App\Domain\Billing\Exceptions\UnknownPaymentException;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use Illuminate\Support\Facades\Log;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\Stripe;
use Throwable;

final class StripeSubscriptionCheckoutGateway implements SubscriptionCheckoutGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey((string) config('services.stripe.secret'));
        Stripe::setApiVersion('2026-02-25.clover');
    }

    public function createCheckoutSession(
        string $tenantId,
        string $planIdentifier,
        string $priceExternalId,
        string $currency,
        int $amountMinor,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): SubscriptionCheckoutSessionResult {
        try {
            $session = CheckoutSession::create([
                'mode' => 'subscription',
                'client_reference_id' => $tenantId,
                'line_items' => [
                    $this->lineItem($planIdentifier, $priceExternalId, $currency, $amountMinor),
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
            ], [
                'idempotency_key' => $idempotencyKey,
            ]);

            $url = is_string($session->url) ? $session->url : null;
            if ($url === null || $url === '') {
                return SubscriptionCheckoutSessionResult::failure('Stripe did not return a Checkout URL.');
            }

            return SubscriptionCheckoutSessionResult::requiresPayment(
                sessionId: (string) $session->id,
                checkoutUrl: $url,
                clientSecret: is_string($session->client_secret) ? $session->client_secret : null,
            );
        } catch (InvalidRequestException $e) {
            Log::error('Invalid Stripe checkout session request', [
                'message' => $e->getMessage(),
                'param' => $e->getStripeParam(),
                'plan_identifier' => $planIdentifier,
            ]);

            throw new UnknownPaymentException('Invalid Stripe checkout configuration', previous: $e);
        } catch (AuthenticationException|ApiConnectionException|RateLimitException $e) {
            Log::error('Stripe checkout unavailable', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayUnavailableException('Stripe checkout is temporarily unavailable', previous: $e);
        } catch (Throwable $e) {
            Log::error('Unexpected Stripe checkout error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw new UnknownPaymentException('Unexpected Stripe checkout error', previous: $e);
        }
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        try {
            $session = CheckoutSession::retrieve($sessionId);
            $status = (string) $session->payment_status;

            if ($status === 'paid') {
                return SubscriptionCheckoutSessionResult::paid((string) $session->id);
            }

            $url = is_string($session->url) ? $session->url : '';

            return SubscriptionCheckoutSessionResult::requiresPayment((string) $session->id, $url);
        } catch (Throwable $e) {
            Log::error('Unable to retrieve Stripe checkout session', [
                'session_id' => $sessionId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return SubscriptionCheckoutSessionResult::failure('Unable to retrieve Stripe checkout session.');
        }
    }

    public function createCustomerPortalSession(string $customerReference, string $returnUrl): SubscriptionPortalSessionResult
    {
        try {
            $session = BillingPortalSession::create([
                'customer' => $customerReference,
                'return_url' => $returnUrl,
            ]);

            if (! is_string($session->url) || $session->url === '') {
                return SubscriptionPortalSessionResult::failure('Stripe did not return a customer portal URL.');
            }

            return SubscriptionPortalSessionResult::success((string) $session->id, $session->url);
        } catch (Throwable $e) {
            Log::error('Unable to create Stripe customer portal session', [
                'customer_reference' => $customerReference,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return SubscriptionPortalSessionResult::failure('Unable to create Stripe customer portal session.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(string $planIdentifier, string $priceExternalId, string $currency, int $amountMinor): array
    {
        if (str_starts_with($priceExternalId, 'price_')) {
            return [
                'price' => $priceExternalId,
                'quantity' => 1,
            ];
        }

        return [
            'price_data' => [
                'currency' => strtolower($currency),
                'product_data' => [
                    'name' => 'Vito Business OS '.$planIdentifier,
                ],
                'recurring' => [
                    'interval' => 'month',
                ],
                'unit_amount' => $amountMinor,
            ],
            'quantity' => 1,
        ];
    }
}
