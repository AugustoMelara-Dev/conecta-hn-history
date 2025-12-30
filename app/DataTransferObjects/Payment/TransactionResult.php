<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Payment;

use App\ValueObjects\Money;

/**
 * TransactionResult - Standardized Payment Response DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Vendor-Neutral Transaction Response           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Each payment provider returns different response formats:     ║
 * ║    - Stripe: PaymentIntent object with status, client_secret, etc.      ║
 * ║    - PayPal: Order object with capture links                           ║
 * ║    - PixelPay: JSON with codigo_transaccion, estado, etc.              ║
 * ║                                                                          ║
 * ║  SOLUTION: All PaymentGateway implementations MUST return this DTO.     ║
 * ║    The consuming code (CheckoutService, OrderController) has ZERO       ║
 * ║    knowledge of which provider was used.                                ║
 * ║                                                                          ║
 * ║  BENEFITS:                                                               ║
 * ║    - Swap Stripe for PixelPay without touching CheckoutService         ║
 * ║    - Unit tests can mock PaymentGateway without Stripe SDK             ║
 * ║    - Tenant-specific gateway selection becomes trivial                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class TransactionResult
{
    public function __construct(
        /** Whether the payment was successful */
        public bool $success,
        
        /** Our internal transaction reference */
        public string $transactionId,
        
        /** Gateway's transaction/charge ID (for refunds, disputes) */
        public string $gatewayTransactionId,
        
        /** Normalized status: 'completed', 'pending', 'failed', 'requires_action' */
        public string $status,
        
        /** Amount charged (confirmed by gateway) */
        public Money $amount,
        
        /** Gateway name for audit trail (e.g., 'stripe', 'pixelpay') */
        public string $gatewayName,
        
        /** Human-readable message (for UI display) */
        public string $message = '',
        
        /** Error code if failed (gateway-specific, for debugging) */
        public ?string $errorCode = null,
        
        /** Decline reason for failed transaction */
        public ?string $declineReason = null,
        
        /** Card last 4 digits (for receipts) */
        public ?string $cardLast4 = null,
        
        /** Card brand (visa, mastercard, etc.) */
        public ?string $cardBrand = null,
        
        /** 3DS/SCA redirect URL if action required */
        public ?string $actionUrl = null,
        
        /** Raw gateway response (for debugging, NOT for business logic) */
        public array $rawResponse = [],
        
        /** Transaction timestamp */
        public ?\DateTimeInterface $processedAt = null,
    ) {}

    /**
     * Factory for successful transaction.
     */
    public static function success(
        string $transactionId,
        string $gatewayTransactionId,
        Money $amount,
        string $gatewayName,
        array $options = []
    ): self {
        return new self(
            success: true,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            status: 'completed',
            amount: $amount,
            gatewayName: $gatewayName,
            message: $options['message'] ?? 'Payment completed successfully',
            cardLast4: $options['card_last4'] ?? null,
            cardBrand: $options['card_brand'] ?? null,
            rawResponse: $options['raw_response'] ?? [],
            processedAt: $options['processed_at'] ?? now(),
        );
    }

    /**
     * Factory for failed transaction.
     */
    public static function failure(
        string $transactionId,
        string $gatewayName,
        string $errorCode,
        string $declineReason,
        array $options = []
    ): self {
        return new self(
            success: false,
            transactionId: $transactionId,
            gatewayTransactionId: $options['gateway_transaction_id'] ?? '',
            status: 'failed',
            amount: $options['amount'] ?? Money::zero(),
            gatewayName: $gatewayName,
            message: $declineReason,
            errorCode: $errorCode,
            declineReason: $declineReason,
            rawResponse: $options['raw_response'] ?? [],
            processedAt: now(),
        );
    }

    /**
     * Factory for transactions requiring additional action (3DS, etc.)
     */
    public static function requiresAction(
        string $transactionId,
        string $gatewayTransactionId,
        string $actionUrl,
        string $gatewayName,
        Money $amount
    ): self {
        return new self(
            success: false,
            transactionId: $transactionId,
            gatewayTransactionId: $gatewayTransactionId,
            status: 'requires_action',
            amount: $amount,
            gatewayName: $gatewayName,
            message: 'Additional authentication required',
            actionUrl: $actionUrl,
            processedAt: now(),
        );
    }

    /**
     * Check if transaction requires user action (3DS, etc.)
     */
    public function requiresUserAction(): bool
    {
        return $this->status === 'requires_action' && !empty($this->actionUrl);
    }

    /**
     * Convert to array for API responses.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
            'amount' => $this->amount->cents(),
            'amount_formatted' => $this->amount->formatted(),
            'message' => $this->message,
            'card_last4' => $this->cardLast4,
            'card_brand' => $this->cardBrand,
            'requires_action' => $this->requiresUserAction(),
            'action_url' => $this->actionUrl,
            'processed_at' => $this->processedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
