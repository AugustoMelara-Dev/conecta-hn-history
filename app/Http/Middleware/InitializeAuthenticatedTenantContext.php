<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Support\Tenancy\RequestTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InitializeAuthenticatedTenantContext
{
    public function __construct(
        private readonly RequestTenantResolver $resolver,
        private readonly TenantContext $tenantContext,
        private readonly PostgresTenantScope $postgresTenantScope,
    ) {}

    /**
     * Initialize the domain tenant context from a tenant the authenticated
     * user is authorized to access. RequestTenantResolver rejects tenant
     * headers that are not backed by an active user membership.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        abort_unless((bool) $tenant->is_active, 404);

        // Bearer tokens are tenant-bound. Cookie-authenticated first-party
        // requests have no currentAccessToken and continue to use membership.
        $accessToken = $request->user()?->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken && $accessToken->exists) {
            abort_unless(
                in_array('tenant:'.$tenant->getKey(), $accessToken->abilities ?? [], true),
                403,
                'Token is not authorized for this tenant.',
            );
        }

        // Calling setTenant on an initialized context also detects tenant hopping.
        $this->tenantContext->setTenant((string) $tenant->getKey());

        app()->instance('currentTenant', $tenant);
        app()->instance('current_tenant', $tenant);

        try {
            $response = $this->postgresTenantScope->run(fn (): Response => $next($request));

            if ($response instanceof StreamedResponse && $response->getCallback() !== null) {
                $callback = Closure::fromCallable($response->getCallback());
                $tenantId = (string) $tenant->getKey();

                $response->setCallback(function () use ($callback, $tenant, $tenantId): void {
                    $this->tenantContext->setTenant($tenantId);
                    app()->instance('currentTenant', $tenant);
                    app()->instance('current_tenant', $tenant);

                    try {
                        $this->postgresTenantScope->run($callback);
                    } finally {
                        $this->clearContext();
                    }
                });
            }

            return $response;
        } finally {
            $this->clearContext();
        }
    }

    private function clearContext(): void
    {
        $this->tenantContext->reset();
        app()->forgetInstance('currentTenant');
        app()->forgetInstance('current_tenant');
    }
}
