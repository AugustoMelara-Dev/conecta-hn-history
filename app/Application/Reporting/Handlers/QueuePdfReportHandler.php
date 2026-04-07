<?php

declare(strict_types=1);

namespace App\Application\Reporting\Handlers;

use App\Application\Reporting\Commands\QueuePdfReportCommand;
use App\Application\Shared\DTOs\ReportRequestData;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;

final class QueuePdfReportHandler
{
    public function handle(QueuePdfReportCommand $command): void
    {
        GeneratePdfReportJob::dispatch(
            data: $this->buildReportRequestData($command),
            requestorId: $command->requestorId,
        )->onQueue('reports');
    }

    private function buildReportRequestData(QueuePdfReportCommand $command): ReportRequestData
    {
        $reportType = strtolower(trim($command->reportType));

        if (! in_array($reportType, ['sales', 'inventory', 'kitchen'], true)) {
            throw new \InvalidArgumentException('Unsupported report type: '.$command->reportType);
        }

        $resolvedStartDate = $command->startDate ?? now()->toDateString();
        $resolvedEndDate = $command->endDate ?? now()->toDateString();

        return new ReportRequestData(
            tenantId: $command->tenantId,
            reportType: $reportType,
            startDate: new \DateTimeImmutable($resolvedStartDate.' 00:00:00'),
            endDate: new \DateTimeImmutable($resolvedEndDate.' 23:59:59'),
        );
    }
}
