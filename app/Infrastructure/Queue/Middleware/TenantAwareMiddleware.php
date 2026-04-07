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
        $previousState = $this->captureState($context);

        try {
            if (property_exists($job, 'tenantId') && $job->tenantId !== null) {
                $this->activateTenantContext($context, $job->tenantId);
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
}
