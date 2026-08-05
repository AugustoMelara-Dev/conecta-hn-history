<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InitializeFilamentTenantContext
{
    /**
     * The only session-backed tenant selection accepted by the Livewire
     * boundary.  The selection is an id/slug pair so a stale or tampered
     * session cannot silently rebind an id to a different tenant slug.
     */
    public const SELECTED_TENANT_SESSION_KEY = 'filament.app.selected_tenant';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PostgresTenantScope $postgresTenantScope,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);
        $ownsContext = false;
        $ownsCurrentTenantBinding = false;
        $ownsCurrentTenantAliasBinding = false;

        if ($tenant instanceof Tenant && $this->tenantIsAuthorizedForSession($request, $tenant)) {
            $this->assertExistingTenantBindingsMatch($tenant);

            // Always call setTenant, even when a surrounding boundary already
            // initialized the context.  Re-selecting another tenant in the
            // same request must raise TenantContextMutationException.
            $wasInitialized = $this->tenantContext->isInitialized();
            $this->tenantContext->setTenant((string) $tenant->getKey());

            $ownsContext = ! $wasInitialized;

            $ownsCurrentTenantBinding = ! app()->bound('currentTenant');
            if ($ownsCurrentTenantBinding) {
                app()->instance('currentTenant', $tenant);
            }

            $ownsCurrentTenantAliasBinding = ! app()->bound('current_tenant');
            if ($ownsCurrentTenantAliasBinding) {
                app()->instance('current_tenant', $tenant);
            }

            // Only an authenticated, active, authorized tenant may become the
            // persisted Livewire selection.  Route payloads never write this
            // value; they are re-authorized by the dedicated wrapper.
            $request->session()->put(self::SELECTED_TENANT_SESSION_KEY, [
                'id' => (string) $tenant->getKey(),
                'slug' => (string) $tenant->slug,
            ]);
        }

        try {
            return $this->postgresTenantScope->run(fn (): Response => $next($request));
        } finally {
            if ($ownsContext) {
                $this->tenantContext->reset();
            }

            if ($ownsCurrentTenantBinding) {
                app()->forgetInstance('currentTenant');
            }

            if ($ownsCurrentTenantAliasBinding) {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    private function tenantIsAuthorizedForSession(Request $request, Tenant $tenant): bool
    {
        if (! (bool) $tenant->is_active) {
            return false;
        }

        $user = $request->user();

        if ($user === null || ! method_exists($user, 'canAccessTenant')) {
            return false;
        }

        return (bool) $user->canAccessTenant($tenant);
    }

    private function assertExistingTenantBindingsMatch(Tenant $tenant): void
    {
        foreach (['currentTenant', 'current_tenant'] as $binding) {
            if (! app()->bound($binding)) {
                continue;
            }

            try {
                $boundTenant = app($binding);
            } catch (\Throwable) {
                abort(403, 'Tenant context binding is invalid.');
            }

            if (! $boundTenant instanceof Tenant
                || (string) $boundTenant->getKey() !== (string) $tenant->getKey()) {
                abort(403, 'Tenant context binding does not match the active tenant.');
            }
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        try {
            $tenant = Filament::getTenant();

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        } catch (\Throwable) {
            // Filament may not have hydrated the tenant yet; fall back to the route parameter.
        }

        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        if (is_string($routeTenant) && $routeTenant !== '') {
            return Tenant::query()
                ->where('slug', $routeTenant)
                ->first();
        }

        return null;
    }
}
