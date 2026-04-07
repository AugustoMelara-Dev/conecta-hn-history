<?php

declare(strict_types=1);

namespace App\Application\Reporting\Commands;

final readonly class QueuePdfReportCommand
{
    public function __construct(
        public string $tenantId,
        public int|string $requestorId,
        public string $reportType,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}
}
