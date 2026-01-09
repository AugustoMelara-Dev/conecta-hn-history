<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Payment;

use App\ValueObjects\Money;

/**
 * PaymentData - Immutable DTO for Payment Request Information
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Vendor-Agnostic Payment Request               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This DTO encapsulates all information needed to initiate a payment,    ║
 * ║  regardless of whether we're using Stripe, PayPal, or PixelPay.         ║
 * ║                                                                          ║
 * ║  The PaymentGateway contract accepts this DTO, not raw arrays or        ║
 * ║  vendor-specific parameters. This ensures:                              ║
 * ║    - Type safety at compile time                                        ║
 * ║    - IDE autocomplete for all fields                                    ║
 * ║    - Validation happens once at DTO construction                        ║
 * ║    - Gateway implementations map DTO fields to vendor-specific formats  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class PaymentData
{
    public function __construct(
        /** Amount to charge (in Money Value Object) */
        public Money $amount,

        /** Payment method token (from frontend SDK) */
        public string $paymentMethodToken,

        /** Customer email for receipts */
        public string $customerEmail,

        /** Customer name for the transaction */
        public string $customerName,

        /** Order reference (for reconciliation) */
        public string $orderId,

        /** Description shown on statement */
        public string $statementDescriptor = 'CONECTAHN',

        /** Additional metadata (idempotency keys, etc.) */
        public array $metadata = [],

        /** Billing address (optional) */
        public ?array $billingAddress = null,
    ) {}

    /**
     * Create from array (useful for request validation).
     *
     * @param  array  $data  Payment data array
     *
     * @throws \InvalidArgumentException When required fields are missing
     */
    public static function fromArray(array $data): self
    {
        // ═══════════════════════════════════════════════════════════════════════
        // VALIDATION: Fail fast with clear error messages
        // ═══════════════════════════════════════════════════════════════════════
        $requiredFields = ['order_id'];
        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw new \InvalidArgumentException("PaymentData: Required field '{$field}' is missing");
            }
        }

        // Amount can be Money object, int (cents), or missing (throws)
        if (! isset($data['amount'])) {
            throw new \InvalidArgumentException("PaymentData: 'amount' is required");
        }
        $amount = $data['amount'] instanceof Money
            ? $data['amount']
            : Money::fromCents((int) $data['amount']);

        // Payment token with fallback keys
        $token = $data['payment_method_token'] ?? $data['token'] ?? null;
        if (empty($token)) {
            throw new \InvalidArgumentException("PaymentData: 'payment_method_token' or 'token' is required");
        }

        // Email with fallback (required for receipts)
        $email = $data['customer_email'] ?? $data['email'] ?? null;
        if (empty($email)) {
            throw new \InvalidArgumentException("PaymentData: 'customer_email' or 'email' is required");
        }

        // Name with fallback (required for transaction)
        $name = $data['customer_name'] ?? $data['name'] ?? 'Customer';

        return new self(
            amount: $amount,
            paymentMethodToken: $token,
            customerEmail: $email,
            customerName: $name,
            orderId: $data['order_id'],
            statementDescriptor: $data['statement_descriptor'] ?? 'CONECTAHN',
            metadata: $data['metadata'] ?? [],
            billingAddress: $data['billing_address'] ?? null,
        );
    }
}
