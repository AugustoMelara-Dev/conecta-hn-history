<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue\Middleware;

use Closure;

/**
 * TenantQueueMiddleware - Restores Tenant Context BEFORE Job Execution
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: This middleware MUST run before any model deserialization    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM:                                                               ║
 * ║    Queue Worker connects to CENTRAL DB by default.                      ║
 * ║    SerializesModels tries to find Order::find(5) in Central DB.         ║
 * ║    Order 5 exists in Tenant A's database, not Central → CRASH.          ║
 * ║                                                                          ║
 * ║  SOLUTION:                                                              ║
 * ║    1. Event carries $tenantId as primitive property                     ║
 * ║    2. This middleware reads $tenantId from job payload                  ║
 * ║    3. Switches to tenant database BEFORE job processing                 ║
 * ║    4. Now SerializesModels can find Order 5 in correct DB               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REGISTRATION in Listener:
 *   public function middleware(): array
 *   {
 *       return [new TenantQueueMiddleware()];
 *   }
 */
class TenantQueueMiddleware
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository
    ) {}

    public function handle(object $job, Closure $next): void
    {
        // Extract tenant ID from the event/job
        $tenantId = $this->extractTenantId($job);

        if ($tenantId) {
            $this->switchToTenant($tenantId);
        }

        $next($job);
    }

    /**
     * Extract tenant ID from job payload.
     */
    protected function extractTenantId(object $job): ?int
    {
        // If job has tenantId property directly
        if (property_exists($job, 'tenantId')) {
            return $job->tenantId;
        }

        // If job wraps an event with tenantId
        if (property_exists($job, 'event') && property_exists($job->event, 'tenantId')) {
            return $job->event->tenantId;
        }

        // If using Laravel's event property
        if (method_exists($job, 'getTenantId')) {
            return $job->getTenantId();
        }

        return null;
    }

    /**
     * Switch application context to the specified tenant.
     */
    protected function switchToTenant(int|string $tenantId): void
    {
        // $tenant = Tenant::find($tenantId); // Replaced by Repository
        $tenant = $this->tenantRepository->findById((string) $tenantId);

        if (! $tenant) {
            throw new \RuntimeException("Tenant {$tenantId} not found. Cannot process queued job.");
        }

        // If using Spatie Laravel-Multitenancy
        if (method_exists($tenant, 'makeCurrent')) {
            $tenant->makeCurrent();

            return;
        }

        // If using Stancl/Tenancy
        if (function_exists('tenancy') && method_exists(tenancy(), 'initialize')) {
            tenancy()->initialize($tenant);

            return;
        }

        // Manual tenant switching (database per tenant)
        // $this->switchDatabase($tenant);
    }
}
