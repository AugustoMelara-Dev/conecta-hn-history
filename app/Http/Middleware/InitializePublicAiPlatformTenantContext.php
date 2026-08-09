<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class InitializePublicAiPlatformTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PostgresTenantScope $postgresTenantScope,
    ) {}

    /**
     * Public discovery is funded by a dedicated platform tenant. Establish that
     * server-configured identity only for the duration of the request so every
     * AI execution and credit row satisfies PostgreSQL RLS without BYPASSRLS.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicAiEnabled = (bool) config('ai.enabled', false)
            && (bool) config('ai.capabilities.public_discovery.enabled', false);
        if (! $publicAiEnabled) {
            return $next($request);
        }

        $platformTenantId = trim((string) config('ai.public.billing_tenant_id', ''));
        abort_unless(Str::isUuid($platformTenantId), 503, 'Public AI billing is not configured.');
        abort_if($this->tenantContext->isInitialized(), 409, 'Tenant context is already initialized.');

        $this->tenantContext->setTenant($platformTenantId);

        try {
            return $this->postgresTenantScope->run(fn (): Response => $next($request));
        } finally {
            $this->tenantContext->reset();
        }
    }
}
