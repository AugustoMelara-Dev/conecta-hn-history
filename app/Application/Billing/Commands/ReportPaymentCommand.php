<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

/**
 * ReportPaymentCommand - Immutable Input Contract for Payment Reporting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SERIALIZATION SAFE: No UploadedFile or binary objects.                  ║
 * ║  QUEUE READY: Can be dispatched to Redis/SQS without memory issues.      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  DEUDA TÉCNICA: `amount` usa float. Preferible usar centavos (int) o     ║
 * ║  una librería de dinero (brick/money) para evitar errores de redondeo.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReportPaymentCommand
{
    public function __construct(
        /** Tenant ID for multi-tenant isolation */
        public int $tenantId,
        
        /** Bank or transaction reference number  */
        public string $paymentReference,
        
        /** Payment amount (TECH DEBT: use Money VO or cents) */
        public float $amount,
        
        /** Bank name or payment method */
        public string $bank,
        
        /**
         * CRITICAL: Path to the staged file (NOT the binary itself).
         * The Controller MUST upload the file to a temporary location
         * before dispatching this command. The Handler will move it
         * to its final destination via DocumentStorageService.
         */
        public ?string $proofOfPaymentPath = null,
    ) {}
}
