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
        $previousState = $this->captureState($context);

        try {
            if ($tenantId !== null && $tenantId !== '') {
                $this->activateTenantContext($context, $tenantId);
            }

            $next($job);
        } finally {
            $this->restoreState($context, $previousState);
        }
    }

    /**
     * @return array{initialized: bool, root: bool, tenantId: string|int|null}
     */
    private function captureState(TenantContext $context): array
    {
        $initialized = $context->isInitialized();
        $root = $initialized && $context->isRoot();

        return [
            'initialized' => $initialized,
            'root' => $root,
            'tenantId' => $initialized && ! $root ? $context->id() : null,
        ];
    }

    private function activateTenantContext(TenantContext $context, string|int $tenantId): void
    {
        if (! $context->isInitialized()) {
            $context->setTenant($tenantId);

            return;
        }

        if ($context->isRoot()) {
            $context->reset();
            $context->setTenant($tenantId);

            return;
        }

        if ($context->id() !== $tenantId) {
            $context->setTenant($tenantId);
        }
    }

    /**
     * @param  array{initialized: bool, root: bool, tenantId: string|int|null}  $state
     */
    private function restoreState(TenantContext $context, array $state): void
    {
        $context->reset();

        if (! $state['initialized']) {
            return;
        }

        if ($state['root']) {
            $context->setAsSystemRoot(isConsoleOrTesting: true);

            return;
        }

        if ($state['tenantId'] !== null) {
            $context->setTenant($state['tenantId']);
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
