<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\Tenant;
use Closure;
use RuntimeException;

final class TenantQueueMiddleware
{
    public function __construct(
        private readonly ?string $tenantId = null,
        private readonly bool $allowTrashed = false,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        $context = app(TenantContext::class);
        $this->clearContext($context);

        try {
            $tenantId = $this->tenantId ?? $this->extractTenantId($job);

            if ($tenantId === null || $tenantId === '') {
                return $next($job);
            }

            $tenantQuery = Tenant::query();
            if ($this->allowTrashed) {
                $tenantQuery->withTrashed();
            }

            $tenant = $tenantQuery->find((string) $tenantId);
            if (! $tenant instanceof Tenant) {
                throw new RuntimeException("Tenant {$tenantId} not found. Cannot process queued job.");
            }

            $context->setTenant((string) $tenant->getKey());
            app()->instance('currentTenant', $tenant);
            app()->instance('current_tenant', $tenant);

            return app(PostgresTenantScope::class)->run(fn (): mixed => $next($job));
        } finally {
            $this->clearContext($context);
        }
    }

    private function clearContext(TenantContext $context): void
    {
        $context->reset();
        app()->forgetInstance('currentTenant');
        app()->forgetInstance('current_tenant');
    }

    private function extractTenantId(object $job): ?string
    {
        if (property_exists($job, 'tenantId')) {
            return $this->normalizeTenantId($job->tenantId);
        }

        if (property_exists($job, 'event') && is_object($job->event) && property_exists($job->event, 'tenantId')) {
            return $this->normalizeTenantId($job->event->tenantId);
        }

        if (method_exists($job, 'getTenantId')) {
            return $this->normalizeTenantId($job->getTenantId());
        }

        return null;
    }

    private function normalizeTenantId(mixed $value): ?string
    {
        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            return (string) $value->toString();
        }

        return null;
    }
}
