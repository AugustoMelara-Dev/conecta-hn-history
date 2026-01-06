<?php

declare(strict_types=1);

namespace App\Application\Jobs\Reporting;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Contracts\LoggerInterface;
use App\Contracts\ReportGenerationInterface;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Traits\GeneratesSignedDownloadUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Str;

final class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use GeneratesSignedDownloadUrls;

    // Note: $afterCommit is defined in Queueable trait (Laravel 11+)
    // Override via afterCommit() method if needed
    public int $tries = 3;
    public int $timeout = 600;
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $reportType,
        public readonly array $filters,
        public readonly int $requestorId
    ) {}

    public function handle(
        ReportGenerationInterface $reportService,
        LoggerInterface $logger,
        FilesystemFactory $storage
    ): void {
        try {
            $tenant = Tenant::findOrFail($this->tenantId);
            $requestor = User::findOrFail($this->requestorId);

            $pdfContent = match ($this->reportType) {
                'sales' => $reportService->generateSalesPdf($tenant, $this->filters),
                'inventory' => $reportService->generateInventoryPdf($tenant),
                'kitchen' => $reportService->generateKitchenPdf($tenant),
                default => throw new \InvalidArgumentException("Invalid report type"),
            };

            $filename = sprintf('tenants/%s/reports/%s_%s.pdf', $tenant->id, $this->reportType, Str::uuid());
            $storage->disk(config('filesystems.exports', 'local'))->put($filename, $pdfContent);

            $expiresAt = now()->addHours(24);
            $downloadUrl = $this->generateDownloadUrl($filename, $expiresAt);

            $requestor->notify(new ReportReadyNotification(
                reportType: $this->reportType,
                downloadUrl: $downloadUrl,
                tenantName: $tenant->name,
                expiresAt: $expiresAt,
            ));
        } catch (ModelNotFoundException $e) {
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
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['reporting', 'pdf', 'tenant:' . $this->tenantId];
    }
}
