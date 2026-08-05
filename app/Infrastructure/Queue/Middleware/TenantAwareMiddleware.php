<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue\Middleware;

use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
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
final class TenantAwareMiddleware
{
    public function handle(object $job, Closure $next): mixed
    {
        return (new TenantQueueMiddleware)->handle($job, $next);
    }
}
