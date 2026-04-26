<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Reporting;

use App\Application\Shared\DTOs\ReportRequestData;
use App\Contracts\LoggerInterface;
use App\Contracts\ReportGenerationInterface;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Traits\GeneratesSignedDownloadUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * GeneratePdfReportJob - PDF Report Generation Use Case
 */
final class GeneratePdfReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use GeneratesSignedDownloadUrls;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly ReportRequestData $data,
        public readonly int|string $requestorId,
    ) {}

    public function uniqueId(): string
    {
        return sprintf(
            'pdf_%s_%s_%s',
            $this->data->tenantId,
            $this->data->reportType,
            $this->data->startDate->format('Y-m-d')
        );
    }

    public function handle(
        ReportGenerationInterface $reportService,
        LoggerInterface $logger,
        FilesystemFactory $storage,
    ): void {
        $tenant = Tenant::query()->find($this->data->tenantId);
        $requestor = User::query()->find($this->requestorId);

        if ($tenant === null || $requestor === null) {
            if ($this->deleteWhenMissingModels) {
                $logger->warning('Skipping PDF report job because tenant or requestor no longer exists.', [
                    'tenant_id' => $this->data->tenantId,
                    'requestor_id' => (string) $this->requestorId,
                    'tenant_found' => $tenant !== null,
                    'requestor_found' => $requestor !== null,
                ]);

                $this->delete();

                return;
            }

            throw new \RuntimeException('Cannot generate report because tenant or requestor is missing.');
        }

        try {
            $filters = [
                'start_date' => $this->data->startDate->format('Y-m-d'),
                'end_date' => $this->data->endDate->format('Y-m-d'),
                'report_type' => $this->data->reportType,
            ];

            $pdfContent = match ($this->data->reportType) {
                'sales' => $reportService->generateSalesPdf($tenant, $filters),
                'inventory' => $reportService->generateInventoryPdf($tenant),
                'kitchen' => $reportService->generateKitchenPdf($tenant),
                default => throw new \InvalidArgumentException('Invalid report type'),
            };

            $filename = sprintf(
                'tenants/%s/reports/%s_%s.pdf',
                (string) $tenant->getKey(),
                $this->data->reportType,
                (string) Str::uuid()
            );

            $storage->disk((string) config('filesystems.exports', 'local'))->put($filename, $pdfContent);

            $expiresAt = now()->addHours(24);
            $downloadUrl = $this->generateDownloadUrl($filename, $expiresAt, $tenant->getKey(), $requestor->getKey());

            $requestor->notify(new ReportReadyNotification(
                reportType: $this->data->reportType,
                downloadUrl: $downloadUrl,
                tenantName: (string) $tenant->getAttribute('name'),
                expiresAt: $expiresAt,
            ));
        } catch (\Throwable $e) {
            $logger->error('PDF report generation failed.', [
                'tenant_id' => $this->data->tenantId,
                'requestor_id' => (string) $this->requestorId,
                'report_type' => $this->data->reportType,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->data->tenantId)];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['reporting', 'pdf', 'tenant:'.$this->data->tenantId];
    }
}
