<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

final class RequestTenantResolver
{
    public function resolve(Request $request): Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenant = $this->tenantFromContainer()
            ?? $this->tenantFromHeader($request)
            ?? ($user->tenant instanceof Tenant ? $user->tenant : null);

        if (! $tenant instanceof Tenant) {
            abort(403, 'No tenant associated with user.');
        }

        if (! $user->canAccessTenant($tenant)) {
            abort(403, 'Unauthorized access to this tenant context.');
        }

        return $tenant;
    }

    public function resolveExplicit(Request $request): Tenant
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenant = $this->tenantFromHeader($request);

        if (! $tenant instanceof Tenant) {
            abort(422, 'Explicit tenant context is required.');
        }

        if (! $user->canAccessTenant($tenant)) {
            abort(403, 'Unauthorized access to this tenant context.');
        }

        return $tenant;
    }

    private function tenantFromContainer(): ?Tenant
    {
        if (! app()->bound('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function tenantFromHeader(Request $request): ?Tenant
    {
        $tenantId = trim((string) $request->header(config('tenancy.resolution.header_name', 'X-Tenant-ID'), ''));

        if ($tenantId === '') {
            return null;
        }

        return Tenant::query()->withoutGlobalScopes()->find($tenantId);
    }
}
