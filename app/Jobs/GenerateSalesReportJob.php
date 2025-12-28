<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\SalesExport;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Traits\GeneratesSignedDownloadUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * GenerateSalesReportJob - Asynchronous Report Generation Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FIRE-AND-FORGET: Frees HTTP worker immediately                         ║
 * ║  NOTIFICATION: User receives download link when ready                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * WORKFLOW:
 * 1. Controller dispatches job with filters
 * 2. Queue worker picks up job (no HTTP timeout risk)
 * 3. Excel::store() saves to disk (local/s3)
 * 4. Signed URL generated for secure download
 * 5. Notification sent to requestor
 *
 * RESOURCE ALLOCATION:
 * Queue workers have separate memory/time limits from FPM.
 * We can safely process 100k+ records without timeout.
 */
class GenerateSalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GeneratesSignedDownloadUrls;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $filters,
        public readonly User $requestor
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('GenerateSalesReportJob: Starting report generation', [
            'tenant_id' => $this->tenant->id,
            'tenant_slug' => $this->tenant->slug,
            'requestor_id' => $this->requestor->id,
            'filters' => $this->filters,
        ]);

        try {
            // ═══════════════════════════════════════════════════════════════
            // STEP 1: Generate unique filename with tenant isolation
            // ═══════════════════════════════════════════════════════════════
            $uuid = (string) Str::uuid();
            $filename = sprintf(
                'reports/%s/ventas_%s.xlsx',
                $this->tenant->slug ?? $this->tenant->id,
                $uuid
            );

            // ═══════════════════════════════════════════════════════════════
            // STEP 2: Generate Excel to disk (not memory)
            // ═══════════════════════════════════════════════════════════════
            Excel::store(
                new SalesExport(
                    tenantId: $this->tenant->id,
                    startDate: $this->filters['start_date'] ?? null,
                    endDate: $this->filters['end_date'] ?? null,
                    status: $this->filters['status'] ?? null
                ),
                $filename,
                'local' // Can be changed to 's3' for production
            );

            Log::info('GenerateSalesReportJob: File stored successfully', [
                'filename' => $filename,
                'tenant_id' => $this->tenant->id,
            ]);

            // ═══════════════════════════════════════════════════════════════
            // STEP 3: Generate signed download URL (valid for 24 hours)
            // ═══════════════════════════════════════════════════════════════
            // NOTE: Storage::temporaryUrl() only works with S3/Azure.
            // For local disk, we use Laravel's signed routes instead.
            $expiresAt = now()->addHours(24);
            $downloadUrl = $this->generateDownloadUrl($filename, $expiresAt);

            // ═══════════════════════════════════════════════════════════════
            // STEP 4: Notify requestor via database/email
            // ═══════════════════════════════════════════════════════════════
            $this->requestor->notify(new ReportReadyNotification(
                reportType: 'sales',
                downloadUrl: $downloadUrl,
                tenantName: $this->tenant->name,
                expiresAt: $expiresAt,
            ));

            Log::info('GenerateSalesReportJob: Notification sent', [
                'requestor_id' => $this->requestor->id,
                'download_url' => $downloadUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateSalesReportJob: Failed to generate report', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateSalesReportJob: Job failed permanently', [
            'tenant_id' => $this->tenant->id,
            'requestor_id' => $this->requestor->id,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Notify admin or requestor about failure
    }
}
