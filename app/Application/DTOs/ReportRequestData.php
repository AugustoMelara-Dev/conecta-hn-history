<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * ReportRequestData - Immutable DTO for Report Generation Requests
 *
 * Used by GeneratePdfReportJob to encapsulate report parameters.
 */
final readonly class ReportRequestData
{
    public function __construct(
        public string $tenantId,
        public string $reportType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate
    ) {}

    /**
     * @param  array{tenant_id: int|string, report_type: string, start_date: string, end_date: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: (string) $data['tenant_id'],
            reportType: $data['report_type'],
            startDate: new \DateTimeImmutable($data['start_date']),
            endDate: new \DateTimeImmutable($data['end_date']),
        );
    }
}
