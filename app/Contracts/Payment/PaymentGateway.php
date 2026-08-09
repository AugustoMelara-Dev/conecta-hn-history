<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DataTransferObjects\Payment\PaymentData;
use App\DataTransferObjects\Payment\TransactionResult;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\PaymentRequiresActionException;
use App\Exceptions\RefundFailedException;
use App\ValueObjects\Money;

/**
 * PaymentGateway Contract - The Strategy Pattern for Multi-Provider Payments
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Dependency Inversion for Payment Processing   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE PROBLEM WITHOUT THIS CONTRACT:                                      ║
 * ║  ┌──────────────────┐                                                   ║
 * ║  │ CheckoutService  │──────────────► StripeService                      ║
 * ║  └──────────────────┘     (Concrete Dependency)                         ║
 * ║                                                                          ║
 * ║  When Honduras regulations require PixelPay instead of Stripe:          ║
 * ║  → Must rewrite CheckoutService, OrderController, WebhookController     ║
 * ║  → Tests break because they need actual Stripe credentials              ║
 * ║  → Different tenants can't use different providers                      ║
 * ║                                                                          ║
 * ║  ─────────────────────────────────────────────────────────────────────── ║
 * ║                                                                          ║
 * ║  THE SOLUTION WITH THIS CONTRACT:                                        ║
 * ║  ┌──────────────────┐       ┌──────────────────────┐                    ║
 * ║  │ CheckoutService  │──────►│  PaymentGateway      │ (Interface)        ║
 * ║  └──────────────────┘       └──────────────────────┘                    ║
 * ║                                      ▲                                   ║
 * ║                    ┌─────────────────┼─────────────────┐                ║
 * ║                    │                 │                 │                ║
 * ║          ┌─────────┴────┐  ┌─────────┴────┐  ┌─────────┴────┐           ║
 * ║          │StripeGateway │  │PixelPayGate. │  │ TestGateway  │           ║
 * ║          └──────────────┘  └──────────────┘  └──────────────┘           ║
 * ║                                                                          ║
 * ║  MULTI-TENANT DYNAMIC BINDING (in ServiceProvider):                     ║
 * ║    $this->app->bind(PaymentGateway::class, function ($app) {            ║
 * ║        $tenant = $app->make(CurrentTenant::class);                      ║
 * ║        return match($tenant->getPaymentProvider()) {                    ║
 * ║            'stripe' => new StripeGateway($tenant->getStripeKey()),      ║
 * ║            'pixelpay' => new PixelPayGateway($tenant->getPixelKey()),   ║
 * ║            default => new StripeGateway(config('stripe.default_key')),  ║
 * ║        };                                                               ║
 * ║    });                                                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in CheckoutService:
 *   public function __construct(private PaymentGateway $gateway) {}
 *   public function processPayment(PaymentData $data): TransactionResult
 *   {
 *       return $this->gateway->charge($data); // Agnostic of Stripe/PixelPay
 *   }
 */
interface PaymentGateway
{
    /**
     * Get the gateway identifier (e.g., 'stripe', 'pixelpay', 'paypal').
     *
     * Used for:
     * - Audit trail logging
     * - Multi-provider reconciliation
     * - Fee calculation per provider
     */
    public function getIdentifier(): string;

    /**
     * Charge a payment method.
     *
     * @param  PaymentData  $data  All payment information (amount, token, customer, etc.)
     * @return TransactionResult NEVER returns vendor-specific objects
     *
     * @throws PaymentFailedException When charge fails irrecoverably
     * @throws PaymentRequiresActionException When 3DS/SCA required
     */
    public function charge(PaymentData $data): TransactionResult;

    /**
     * Refund a previous transaction (full or partial).
     *
     * @param  string  $transactionId  The gateway's transaction ID (from TransactionResult)
     * @param  Money|null  $amount  Amount to refund (null = full refund)
     * @param  string  $reason  Refund reason for audit trail
     * @return TransactionResult Result of the refund operation
     *
     * @throws RefundFailedException When refund fails
     */
    public function refund(
        string $transactionId,
        ?Money $amount = null,
        string $reason = ''
    ): TransactionResult;

    /**
     * Verify webhook signature and parse event.
     *
     * @param  string  $payload  Raw webhook body
     * @param  string  $signature  Signature header from gateway
     * @return array Parsed and validated event data
     *
     * @throws InvalidWebhookSignatureException On signature mismatch
     */
    public function parseWebhook(string $payload, string $signature): array;

    /**
     * Check if the gateway supports a specific feature.
     *
     * Features: 'refunds', 'partial_refunds', '3ds', 'recurring', 'payouts'
     */
    public function supports(string $feature): bool;

    /**
     * Get supported currencies for this gateway.
     *
     * @return array<string> ISO 4217 currency codes ['HNL', 'USD', 'EUR']
     */
    public function getSupportedCurrencies(): array;

    /**
     * Retrieve transaction details from gateway (for reconciliation).
     *
     * @param  string  $transactionId  Gateway's transaction ID
     * @return TransactionResult Current state of the transaction
     */
    public function getTransaction(string $transactionId): TransactionResult;
}
