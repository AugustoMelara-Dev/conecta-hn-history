<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformAiRegistryConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenantId = config('ai.public.billing_tenant_id');
        if (! is_string($tenantId)
            || ! Str::isUuid($tenantId)
            || ! DB::table('tenants')->where('id', $tenantId)->exists()) {
            return new JsonResponse([
                'code' => 'ai_configuration_unavailable',
            ], 503);
        }

        return $next($request);
    }
}
