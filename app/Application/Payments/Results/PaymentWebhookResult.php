<?php

declare(strict_types=1);

namespace App\Application\Payments\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * PaymentWebhookResult - Immutable Output Contract for Webhook Processing
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FINANCIAL CRITICAL: Provides deterministic status for gateway ACK.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class PaymentWebhookResult
{
    public function __construct(
        public string $providerTransactionId,
        public string $internalStatus,
        public bool $orderWasUpdated,
        public ?string $receiptUrl = null,
    ) {}

    /**
     * Factory for duplicate webhooks.
     */
    public static function duplicate(string $transactionId): self
    {
        return new self(
            providerTransactionId: $transactionId,
            internalStatus: 'DUPLICATE',
            orderWasUpdated: false
        );
    }

    /**
     * Factory for successfully processed processed webhooks (PAID/FAILED).
     */
    public static function processed(
        string $transactionId,
        string $status,
        bool $updatedOrder = true,
        ?string $receiptUrl = null
    ): self {
        return new self(
            providerTransactionId: $transactionId,
            internalStatus: $status,
            orderWasUpdated: $updatedOrder,
            receiptUrl: $receiptUrl
        );
    }
}
