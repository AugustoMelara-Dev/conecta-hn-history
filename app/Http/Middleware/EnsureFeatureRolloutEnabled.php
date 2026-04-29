<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Release\FeatureRolloutManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureFeatureRolloutEnabled
{
    public function __construct(
        private FeatureRolloutManager $rollouts,
    ) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        if (! $this->rollouts->enabled($featureKey, $this->resolveTenant($request))) {
            abort(404);
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        foreach (['tenant', 'tenant_slug', 'negocio'] as $parameter) {
            $value = $request->route($parameter);

            if ($value instanceof Tenant) {
                return $value;
            }

            if (is_string($value) && $value !== '') {
                return Tenant::query()->where('slug', $value)->first();
            }
        }

        $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($currentTenant instanceof Tenant) {
            return $currentTenant;
        }

        $user = $request->user();

        if ($user !== null && method_exists($user, 'tenant')) {
            return $user->tenant;
        }

        return null;
    }
}
