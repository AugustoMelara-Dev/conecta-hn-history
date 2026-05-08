<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue\Middleware;

use App\Domain\Shared\Services\TenantContext;
use Closure;

/**
 * TenantAwareMiddleware - Queue Job Middleware
 *
 * RELOCATED FROM: App\Infrastructure\Jobs\Middleware
 * REASON: Jobs directory is for ShouldQueue classes only
 *
 * Checks if the Job has a $tenantId property and restores context.
 * Ensures strict cleanup after execution.
 */
class TenantAwareMiddleware
{
    public function handle(object $job, Closure $next): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        try {
            if (property_exists($job, 'tenantId') && $job->tenantId !== null) {
                // Restore Context
                $context->setTenant((string) $job->tenantId);
            }

            $next($job);
        } finally {
            // HYGIENE: Always clean up the worker process
            $context->reset();
        }
    }
}
