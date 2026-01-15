<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

/**
 * ReportPaymentCommand - Immutable Input Contract for Payment Reporting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FINANCIAL PRECISION: Uses integers (cents) to avoid IEEE 754 errors.    ║
 * ║  IDEMPOTENT: Supports client-generated idempotency key for replay safety.║
 * ║  SERIALIZATION SAFE: No UploadedFile or binary objects.                  ║
 * ║  QUEUE READY: Can be dispatched to Redis/SQS without memory issues.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReportPaymentCommand
{
    public function __construct(
        /** Tenant ID for multi-tenant isolation */
        public int $tenantId,
        
        /** Bank or transaction reference number  */
        public string $paymentReference,
        
        /** Payment amount in CENTS (e.g. 1999 for $19.99) */
        public int $amountInCents,
        
        /** ISO 4217 Currency Code (e.g. USD, HNL) */
        public string $currency,
        
        /** Bank name or payment method */
        public string $bank,
        
        /**
         * IDEMPOTENCY KEY: Client-generated unique identifier for this payment attempt.
         * Use UUID v4 or similar. Prevents double-processing on network retries.
         * If null, system will not enforce idempotency (legacy compatibility).
         */
        public ?string $idempotencyKey = null,
        
        /**
         * CRITICAL: Path to the staged file (NOT the binary itself).
         * The Controller MUST upload the file to a temporary location
         * before dispatching this command. The Handler will move it
         * to its final destination via DocumentStorageService.
         */
        public ?string $proofOfPaymentPath = null,
    ) {}
}
