<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Middleware\TenantAwareMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TenantJob - Base Class for Multi-Tenant Async Tasks
 *
 * Automatically captures the current Tenant Context upon instantiation
 * and restores it during execution via middleware.
 */
abstract class TenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tenantId;

    public function __construct()
    {
        // Capture Context at Dispatch Time
        $this->tenantId = app(TenantContext::class)->id();
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new TenantAwareMiddleware];
    }
}
