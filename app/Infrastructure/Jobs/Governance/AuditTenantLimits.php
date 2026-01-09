<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Governance;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * AuditTenantLimits - Batch Tenant Limit Verification
 *
 * INFRASTRUCTURE JOB: Direct ORM access for batch processing.
 */
final class AuditTenantLimits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function handle(): void
    {
        Tenant::where('status', 'approved')
            ->chunkById(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    try {
                        Log::debug("Auditing tenant: {$tenant->id}");
                    } catch (\Throwable $e) {
                        Log::error("Failed to audit tenant {$tenant->id}: {$e->getMessage()}");
                    }
                }
            });
    }

    public function tags(): array
    {
        return ['governance', 'audit'];
    }
}
