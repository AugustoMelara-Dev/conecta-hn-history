<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments\Stripe;

use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;
use App\Domain\Billing\Exceptions\PaymentGatewayUnavailableException;
use App\Domain\Billing\Exceptions\UnknownPaymentException;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class StripeSubscriptionCheckoutGateway implements SubscriptionCheckoutGatewayInterface
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function createCheckoutSession(
        string $tenantId,
        string $planIdentifier,
        string $priceExternalId,
        string $currency,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): SubscriptionCheckoutSessionResult {
        try {
            $session = $this->stripePost('/checkout/sessions', [
                'mode' => 'subscription',
                'client_reference_id' => $tenantId,
                'line_items[0][price]' => $priceExternalId,
                'line_items[0][quantity]' => 1,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ] + $this->flattenMetadata('metadata', $metadata)
                + $this->flattenMetadata('subscription_data[metadata]', $metadata + [
                    'tenant_id' => $tenantId,
                    'plan_identifier' => $planIdentifier,
                ]), [
                    'idempotency_key' => $idempotencyKey,
                ]);

            if (! is_string($session['url'] ?? null) || $session['url'] === '') {
                return SubscriptionCheckoutSessionResult::failure('Stripe did not return a Checkout URL.');
            }

            return SubscriptionCheckoutSessionResult::requiresPayment(
                sessionId: (string) ($session['id'] ?? ''),
                checkoutUrl: $session['url'],
                clientSecret: is_string($session['client_secret'] ?? null) ? $session['client_secret'] : null,
            );
        } catch (RequestException $e) {
            Log::error('Invalid Stripe checkout session request', [
                'message' => $e->getMessage(),
                'response' => $e->response?->json(),
            ]);

            throw new UnknownPaymentException('Invalid Stripe checkout configuration', previous: $e);
        } catch (ConnectionException $e) {
            Log::error('Stripe checkout unavailable', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new PaymentGatewayUnavailableException('Stripe checkout is temporarily unavailable', previous: $e);
        } catch (\Throwable $e) {
            Log::error('Unexpected Stripe checkout error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new UnknownPaymentException('Unexpected Stripe checkout error', previous: $e);
        }
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        try {
            $session = $this->stripeGet('/checkout/sessions/'.rawurlencode($sessionId));

            if (($session['payment_status'] ?? null) === 'paid') {
                return SubscriptionCheckoutSessionResult::paid((string) ($session['id'] ?? $sessionId));
            }

            return SubscriptionCheckoutSessionResult::requiresPayment(
                sessionId: (string) ($session['id'] ?? $sessionId),
                checkoutUrl: is_string($session['url'] ?? null) ? $session['url'] : '',
                clientSecret: is_string($session['client_secret'] ?? null) ? $session['client_secret'] : null,
            );
        } catch (\Throwable $e) {
            Log::error('Unable to retrieve Stripe checkout session', [
                'session_id' => $sessionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return SubscriptionCheckoutSessionResult::failure('Unable to retrieve Stripe checkout session.');
        }
    }

    public function createCustomerPortalSession(string $customerId, string $returnUrl): SubscriptionPortalSessionResult
    {
        try {
            $session = $this->stripePost('/billing_portal/sessions', [
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);

            if (! is_string($session['url'] ?? null) || $session['url'] === '') {
                return SubscriptionPortalSessionResult::failure('Stripe did not return a customer portal URL.');
            }

            return SubscriptionPortalSessionResult::success((string) ($session['id'] ?? ''), $session['url']);
        } catch (\Throwable $e) {
            Log::error('Unable to create Stripe customer portal session', [
                'customer_id' => $customerId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return SubscriptionPortalSessionResult::failure('Unable to create Stripe customer portal session.');
        }
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function stripePost(string $path, array $form, array $headers = []): array
    {
        return Http::asForm()
            ->withToken($this->secret())
            ->withHeaders($headers + ['Stripe-Version' => '2026-02-25.clover'])
            ->post(self::API_BASE.$path, $form)
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    private function stripeGet(string $path): array
    {
        return Http::withToken($this->secret())
            ->withHeaders(['Stripe-Version' => '2026-02-25.clover'])
            ->get(self::API_BASE.$path)
            ->throw()
            ->json();
    }

    private function secret(): string
    {
        $secret = (string) config('services.stripe.secret', '');

        if ($secret === '') {
            throw new PaymentGatewayUnavailableException('Stripe secret is not configured.');
        }

        return $secret;
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array<string, string>
     */
    private function flattenMetadata(string $prefix, array $metadata): array
    {
        $flattened = [];

        foreach ($metadata as $key => $value) {
            $flattened[$prefix.'['.$key.']'] = (string) $value;
        }

        return $flattened;
    }
}
