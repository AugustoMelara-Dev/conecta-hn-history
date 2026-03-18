<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue\Middleware;

use App\Domain\Shared\Services\TenantContext;
use Closure;

/**
 * Restores tenant context before queued job execution.
 */
final class TenantQueueMiddleware
{
    public function __construct(
        private readonly string|int|null $tenantId = null,
    ) {}

    public function handle(object $job, Closure $next): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        $tenantId = $this->tenantId ?? $this->extractTenantId($job);

        try {
            if ($tenantId !== null && $tenantId !== '') {
                $context->setTenant($tenantId);
            }

            $next($job);
        } finally {
            $context->reset();
        }
    }

    protected function extractTenantId(object $job): string|int|null
    {
        if (property_exists($job, 'tenantId')) {
            /** @var mixed $tenantId */
            $tenantId = $job->tenantId;

            return is_string($tenantId) || is_int($tenantId) ? $tenantId : null;
        }

        if (property_exists($job, 'event') && is_object($job->event) && property_exists($job->event, 'tenantId')) {
            /** @var mixed $tenantId */
            $tenantId = $job->event->tenantId;

            return is_string($tenantId) || is_int($tenantId) ? $tenantId : null;
        }

        if (method_exists($job, 'getTenantId')) {
            /** @var mixed $tenantId */
            $tenantId = $job->getTenantId();

            return is_string($tenantId) || is_int($tenantId) ? $tenantId : null;
        }

        return null;
    }
}
