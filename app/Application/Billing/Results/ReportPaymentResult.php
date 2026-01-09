<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

use Illuminate\Http\Request;

/**
 * ReportPaymentResult - Pure Immutable DTO for Payment Reporting
 *
 * SERIALIZABLE: Uses string path instead of UploadedFile object.
 * Controller must pre-process file uploads before instantiating this DTO.
 */
final readonly class ReportPaymentResult
{
    public function __construct(
        public int $tenantId,
        public string $bank,
        public string $referenceNumber,
        public float $amount,
        public ?string $receiptPath
    ) {}

    /**
     * Factory method to create DTO from validated request.
     * Expects receiptPath to be pre-processed by controller.
     */
    public static function fromRequest(Request $request, int $tenantId, ?string $receiptPath = null): self
    {
        $validated = $request->validated();

        return new self(
            tenantId: $tenantId,
            bank: $validated['bank'],
            referenceNumber: $validated['reference_number'],
            amount: (float) $validated['amount'],
            receiptPath: $receiptPath
        );
    }
}
