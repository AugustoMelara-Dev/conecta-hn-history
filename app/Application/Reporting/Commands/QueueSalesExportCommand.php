<?php

declare(strict_types=1);

namespace App\Application\Reporting\Commands;

final readonly class QueueSalesExportCommand
{
    /**
     * @param  array{start_date?: ?string, end_date?: ?string, status?: ?string}  $criteriaData
     */
    public function __construct(
        public int|string $requestorId,
        public string $tenantId,
        public array $criteriaData,
        public string $requestIp,
        public string $userAgent,
    ) {}
}
