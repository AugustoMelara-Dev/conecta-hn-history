<?php

declare(strict_types=1);

use App\Application\Reporting\Commands\QueuePdfReportCommand;
use App\Application\Reporting\Commands\QueueSalesExportCommand;
use App\Application\Reporting\Handlers\QueuePdfReportHandler;
use App\Application\Reporting\Handlers\QueueSalesExportHandler;
use App\Infrastructure\Jobs\Exports\GenerateSalesExport;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use Illuminate\Support\Facades\Queue;

it('queues pdf reports on the reports queue with normalized payload', function () {
    Queue::fake();

    app(QueuePdfReportHandler::class)->handle(new QueuePdfReportCommand(
        tenantId: 'tenant-reporting',
        requestorId: 42,
        reportType: 'Sales',
        startDate: '2026-03-01',
        endDate: '2026-03-31',
    ));

    Queue::assertPushed(GeneratePdfReportJob::class, function (GeneratePdfReportJob $job): bool {
        return $job->queue === 'reports'
            && $job->requestorId === 42
            && $job->data->tenantId === 'tenant-reporting'
            && $job->data->reportType === 'sales'
            && $job->data->startDate->format('Y-m-d H:i:s') === '2026-03-01 00:00:00'
            && $job->data->endDate->format('Y-m-d H:i:s') === '2026-03-31 23:59:59';
    });
});

it('rejects unsupported pdf report types before dispatching the job', function () {
    Queue::fake();

    expect(fn () => app(QueuePdfReportHandler::class)->handle(new QueuePdfReportCommand(
        tenantId: 'tenant-reporting',
        requestorId: 42,
        reportType: 'unknown-report',
    )))->toThrow(InvalidArgumentException::class, 'Unsupported report type: unknown-report');

    Queue::assertNothingPushed();
});

it('queues sales exports on the exports queue with the provided criteria', function () {
    Queue::fake();

    app(QueueSalesExportHandler::class)->handle(new QueueSalesExportCommand(
        requestorId: 24,
        tenantId: 'tenant-exports',
        criteriaData: [
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-29',
            'status' => 'paid',
        ],
        requestIp: '127.0.0.1',
        userAgent: 'Codex Test Agent',
    ));

    Queue::assertPushed(GenerateSalesExport::class, function (GenerateSalesExport $job): bool {
        return $job->queue === 'exports'
            && $job->userId === 24
            && $job->tenantId === 'tenant-exports'
            && $job->criteriaData === [
                'start_date' => '2026-02-01',
                'end_date' => '2026-02-29',
                'status' => 'paid',
            ]
            && $job->requestIp === '127.0.0.1'
            && $job->userAgent === 'Codex Test Agent';
    });
});
