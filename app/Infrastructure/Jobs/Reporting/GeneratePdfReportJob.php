<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Reporting;

use App\Contracts\LoggerInterface;
use App\Contracts\ReportGenerationInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Notifications\ReportReadyNotification;
use App\Traits\GeneratesSignedDownloadUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Domain\Identity\Exceptions\TenantNotFoundException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use Throwable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * GeneratePdfReportJob - PDF Report Generation Use Case
 *
 * APPLICATION JOB: Uses Repository pattern for entity hydration.
 * IDEMPOTENCY: Uses ShouldBeUnique with tenant-type-date composite key.
 */
final class GeneratePdfReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use GeneratesSignedDownloadUrls;

    public int $tries = 3;

    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly \App\Application\Shared\DTOs\ReportRequestData $data,
        public readonly int $requestorId
    ) {}

    /**
     * Unique job identifier (tenant + report type + date composite).
     */
    public function uniqueId(): string
    {
        return sprintf('pdf_%s_%s_%s',
            $this->data->tenantId,
            $this->data->reportType,
            $this->data->startDate->format('Y-m-d')
        );
    }

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        UserRepositoryInterface $userRepository,
        ReportGenerationInterface $reportService,
        LoggerInterface $logger,
        FilesystemFactory $storage
    ): void {
        try {
            // Access tenantId from DTO
            $tenant = $tenantRepository->findById($this->data->tenantId);
            if ($tenant === null) {
                throw new TenantNotFoundException("Tenant {$this->data->tenantId} not found");
            }

            $requestor = $userRepository->findById($this->requestorId);
            if ($requestor === null) {
                throw new UserNotFoundException("User {$this->requestorId} not found");
            }

            // Map DTO back to filters array for service compatibility
            // TODO: In future refactor, update ReportGenerationInterface to accept DTOs
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

            $filename = sprintf('tenants/%s/reports/%s_%s.pdf', $tenant->id, $this->data->reportType, Str::uuid());
            $storage->disk(config('filesystems.exports', 'local'))->put($filename, $pdfContent);

            $expiresAt = now()->addHours(24);
            $downloadUrl = $this->generateDownloadUrl($filename, $expiresAt);

            $requestor->notify(new ReportReadyNotification(
                reportType: $this->data->reportType,
                downloadUrl: $downloadUrl,
                tenantName: $tenant->name,
                expiresAt: $expiresAt,
            ));
        } catch (TenantNotFoundException | UserNotFoundException $e) {
            if ($this->deleteWhenMissingModels) {
                $this->delete();

                return;
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->data->tenantId)];
    }

    public function tags(): array
    {
        return ['reporting', 'pdf', 'tenant:'.$this->data->tenantId];
    }
}
