<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

/**
 * ReportPaymentResult - Immutable Result DTO for Payment Reporting
 *
 * DOMAIN: Billing
 * TYPE: Pure DTO
 * INVARIANT: Contains only validated scalar data ready for domain processing.
 */
final readonly class ReportPaymentResult
{
    /**
     * @param int $tenantId
     * @param string $bank
     * @param string $referenceNumber
     * @param float $amount
     * @param string|null $receiptPath
     */
    public function __construct(
        public int $tenantId,
        public string $bank,
        public string $referenceNumber,
        public float $amount,
        public ?string $receiptPath
    ) {}
}
