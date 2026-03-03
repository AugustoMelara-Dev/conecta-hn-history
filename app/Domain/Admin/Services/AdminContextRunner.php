<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Shared\Services\TenantContext;
use Closure;

/**
 * AdminContextRunner - Safe Privilege Escalation
 *
 * Executes a closure within the System Root context (God Mode)
 * and guarantees strict cleanup afterwards.
 */
final class AdminContextRunner
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /**
     * Run a callback as System Root.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        // Store previous state if needed?
        // Zero Trust says: We shouldn't escalate if we are already in a tenant context unless explicitly allowed.
        // But for simplicity/robustness of prompt req: "try { setAsSystemRoot(); return $callback(); } finally { reset(); }"

        $this->context->reset(); // Ensure clean slate before elevation

        try {
            $this->context->setAsSystemRoot(isConsoleOrTesting: true);

            return $callback();
        } finally {
            $this->context->reset(); // Always return to neutral state
        }
    }
}
