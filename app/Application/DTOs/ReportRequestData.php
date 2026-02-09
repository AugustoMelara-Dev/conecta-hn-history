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
        public int $tenantId,
        public string $reportType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate
    ) {}
}
