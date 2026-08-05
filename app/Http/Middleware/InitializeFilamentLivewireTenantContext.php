<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Tenancy\PostgresTenantScope;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establish the tenant boundary for Filament's global Livewire update route.
 *
 * Livewire updates do not carry Filament's `{tenant}` route parameter.  The
 * component snapshot is therefore used only to classify the active panel and
 * compare its tenant slug with the authenticated session selection.  Tenant
 * identity always comes from the session's id/slug pair and is rehydrated and
 * authorized again for every update.
 */
final class InitializeFilamentLivewireTenantContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PostgresTenantScope $postgresTenantScope,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('*livewire.update')) {
            return $next($request);
        }

        $snapshotClassification = $this->classifyPayload($request);

        // Admin, public, and malformed non-app payloads do not belong to this
        // tenant boundary.  Leave them to their own middleware topology.
        if ($snapshotClassification === null) {
            return $next($request);
        }

        [$snapshotSlug, $hasMixedPanels] = $snapshotClassification;

        if ($hasMixedPanels || $snapshotSlug === null) {
            abort(403, 'Livewire tenant context is invalid.');
        }

        $selection = $request->session()->get(InitializeFilamentTenantContext::SELECTED_TENANT_SESSION_KEY);

        if (! is_array($selection)
            || ! is_string($selection['id'] ?? null)
            || ! is_string($selection['slug'] ?? null)
            || $selection['id'] === ''
            || $selection['slug'] === '') {
            abort(403, 'Livewire tenant context is unavailable.');
        }

        if (! hash_equals($selection['slug'], $snapshotSlug)) {
            abort(403, 'Livewire tenant context does not match the active panel.');
        }

        $user = $request->user();
        if ($user === null || ! method_exists($user, 'canAccessTenant')) {
            abort(403, 'Livewire tenant context requires an authenticated owner.');
        }

        // Rehydrate by both id and slug.  The snapshot slug is a routing
        // classification only; it is never used to choose a tenant id.
        $tenant = Tenant::query()
            ->whereKey($selection['id'])
            ->where('slug', $selection['slug'])
            ->first();

        if (! $tenant instanceof Tenant
            || ! (bool) $tenant->is_active
            || ! $user->canAccessTenant($tenant)) {
            abort(403, 'Livewire tenant context is not authorized.');
        }

        $wasInitialized = $this->tenantContext->isInitialized();
        $this->assertExistingTenantBindingsMatch($tenant);
        $ownsCurrentTenantBinding = ! app()->bound('currentTenant');
        $ownsCurrentTenantAliasBinding = ! app()->bound('current_tenant');

        // Always call setTenant so a pre-existing context for another tenant
        // fails closed as tenant hopping.
        $this->tenantContext->setTenant((string) $tenant->getKey());
        if ($ownsCurrentTenantBinding) {
            app()->instance('currentTenant', $tenant);
        }
        if ($ownsCurrentTenantAliasBinding) {
            app()->instance('current_tenant', $tenant);
        }

        try {
            return $this->postgresTenantScope->run(fn (): Response => $next($request));
        } finally {
            if (! $wasInitialized) {
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

    /**
     * Resolve the tenant slug by matching each snapshot's path and method
     * against the real application route collection.  Snapshot paths are a
     * classifier only: the route must be a tenant-bearing `filament.app.*`
     * route, and the slug comes from the matched route parameter.
     *
     * @return array{0: string|null, 1: bool}|null
     */
    private function classifyPayload(Request $request): ?array
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return null;
        }

        $tenantSlugs = [];
        $hasOtherClassification = false;
        $hasUnresolvableAppSnapshot = false;

        foreach ($components as $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                $hasUnresolvableAppSnapshot = true;
                continue;
            }

            try {
                $snapshot = json_decode($component['snapshot'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $hasUnresolvableAppSnapshot = true;
                continue;
            }

            $memo = is_array($snapshot) ? ($snapshot['memo'] ?? null) : null;
            $path = is_array($memo) ? ($memo['path'] ?? null) : null;
            $method = is_array($memo) ? ($memo['method'] ?? null) : null;

            if (! is_string($path) || $path === '' || ! is_string($method) || $method === '') {
                $hasUnresolvableAppSnapshot = true;
                continue;
            }

            $normalizedPath = ltrim($path, '/');
            $isCandidateAppPath = $normalizedPath === 'app' || str_starts_with($normalizedPath, 'app/');

            try {
                $snapshotRequest = Request::create('/'.$normalizedPath, strtoupper($method));
                $matchedRoute = app('router')->getRoutes()->match($snapshotRequest);
                $matchedRoute->bind($snapshotRequest);
            } catch (\Throwable) {
                if ($isCandidateAppPath) {
                    $hasUnresolvableAppSnapshot = true;
                } else {
                    $hasOtherClassification = true;
                }

                continue;
            }

            $routeName = (string) $matchedRoute->getName();
            $isAppRoute = str_starts_with($routeName, 'filament.app.');
            $declaresTenant = in_array('tenant', $matchedRoute->parameterNames(), true);

            if (! $isAppRoute || ! $declaresTenant) {
                // Login, password-reset, email-verification, admin, and
                // public routes are not tenant contexts.  They are harmless
                // when all components classify this way, but become a
                // fail-closed mixed payload beside a tenant component.
                $hasOtherClassification = true;
                continue;
            }

            $slug = $matchedRoute->parameter('tenant');
            if (! is_string($slug) || $slug === '') {
                $hasUnresolvableAppSnapshot = true;
                continue;
            }

            $tenantSlugs[] = $slug;
        }

        if ($tenantSlugs === []) {
            return $hasUnresolvableAppSnapshot ? [null, true] : null;
        }

        $uniqueSlugs = array_values(array_unique($tenantSlugs));
        $mixed = $hasOtherClassification || $hasUnresolvableAppSnapshot || count($uniqueSlugs) !== 1;

        return [$mixed ? null : $uniqueSlugs[0], $mixed];
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
}
