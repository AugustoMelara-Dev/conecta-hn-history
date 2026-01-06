<?php

declare(strict_types=1);

namespace App\Domain\ReportingAndExports\Jobs;

use App\Domain\Identity\Models\User;
use App\Domain\TenantGovernance\Models\Tenant;
use App\Traits\GeneratesSignedDownloadUrls;
use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Log;

/**
 * GenerateSalesReportJob
 *
 * @deprecated Use App\Domain\ReportingAndExports\Jobs\GenerateSalesExport instead.
 */
/**
 * ⛔⛔⛔ ARCHITECTURAL WARNING: DO NOT COPY THIS PATTERN ⛔⛔⛔
 *
 * This class exists SOLELY as a backward-compatibility PROXY for old queued jobs.
 * It is a violation of the Clean Architecture layers (Infrastructure inside Domain).
 *
 * @deprecated THIS CLASS IS DEAD CODE. New jobs must be created in App\Application\Jobs.
 * @see \App\Application\Jobs\Exports\GenerateSalesExport
 */
class GenerateSalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GeneratesSignedDownloadUrls;

    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly array $criteria = []
    ) {}

    public function handle(): void
    {
        try {
            // PROXY PATTERN implementation for Legacy Jobs
            // Ensures zero data loss for jobs queued before deployment.
            
            \App\Application\Jobs\Exports\GenerateSalesExport::dispatch(
                $this->user->id,
                $this->tenant->id,
                $this->criteria,
                '127.0.0.1',        // Default for legacy jobs
                'LegacyJobWorker'   // Audit trail marker
            );

            Log::info("LEGACY PROXY SUCCESS: Redirected GenerateSalesReportJob to GenerateSalesExport", [
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id
            ]);

        } catch (\Throwable $e) {
            // CRITICAL: Prevent silent failure.
            // If mapping fails, log loudly and move to failed_jobs table.
            Log::critical("LEGACY PROXY FAILED: Could not migrate job payload.", [
                'error' => $e->getMessage(),
                'payload' => $this->criteria
            ]);
            
            $this->fail($e);
        }
    }
}
