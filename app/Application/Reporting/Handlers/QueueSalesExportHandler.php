<?php

declare(strict_types=1);

namespace App\Application\Reporting\Handlers;

use App\Application\Reporting\Commands\QueueSalesExportCommand;
use App\Infrastructure\Jobs\Exports\GenerateSalesExport;

final class QueueSalesExportHandler
{
    public function handle(QueueSalesExportCommand $command): void
    {
        GenerateSalesExport::dispatch(
            userId: $command->requestorId,
            tenantId: $command->tenantId,
            criteriaData: $command->criteriaData,
            requestIp: $command->requestIp,
            userAgent: $command->userAgent,
        )->onQueue('exports');
    }
}
