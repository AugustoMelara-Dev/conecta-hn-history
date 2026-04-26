<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class MeasureHttpPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('performance.http.enabled', false)) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        if ((bool) config('performance.http.query_metrics_enabled', false)) {
            DB::listen(static function ($query) use (&$queryCount, &$queryTimeMs): void {
                $queryCount++;
                $queryTimeMs += (float) ($query->time ?? 0.0);
            });
        }

        /** @var Response $response */
        $response = $next($request);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $route = $request->route();
        $routeName = $route?->getName() ?: 'unnamed';
        $routeUri = method_exists($route, 'uri') ? (string) $route->uri() : $request->path();

        if (! $this->shouldRecord($routeName, $routeUri, $durationMs)) {
            return $response;
        }

        Log::info('http.request.completed', [
            'event' => 'http.request.completed',
            'duration_ms' => $durationMs,
            'status' => $response->getStatusCode(),
            'method' => $request->method(),
            'route_name' => $routeName,
            'route_uri' => $routeUri,
            'surface' => $this->surface($routeName, $routeUri),
            'tenant_id' => $this->tenantId($request),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'trace_id' => (string) ($request->headers->get('x-trace-id') ?: $request->headers->get('x-request-id') ?: ''),
            'query_count' => $queryCount,
            'query_time_ms' => round($queryTimeMs, 2),
        ]);

        return $response;
    }

    private function shouldRecord(string $routeName, string $routeUri, int $durationMs): bool
    {
        $sampleRate = max(0.0, min(1.0, (float) config('performance.http.sample_rate', 1.0)));
        $slowThreshold = max(0, (int) config('performance.http.always_log_slow_ms', 1000));

        if ($durationMs >= $slowThreshold) {
            return true;
        }

        if ($sampleRate >= 1.0) {
            return true;
        }

        return mt_rand() / mt_getrandmax() <= $sampleRate;
    }

    private function surface(string $routeName, string $routeUri): string
    {
        $needle = $routeName.' '.$routeUri;

        return match (true) {
            str_contains($needle, 'dashboard') || str_contains($needle, 'app') => 'dashboard',
            str_contains($needle, 'report') || str_contains($needle, 'export') => 'reports',
            str_contains($needle, 'campaign') || str_contains($needle, 'marketing') => 'campaigns',
            str_contains($needle, 'guide') || str_contains($needle, 'business.chat') || str_contains($needle, 'ai/business') => 'chat',
            default => 'other',
        };
    }

    private function tenantId(Request $request): ?string
    {
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            if (is_object($tenant) && isset($tenant->id)) {
                return (string) $tenant->id;
            }
        }

        $userTenant = $request->user()?->tenant_id;

        return is_scalar($userTenant) && $userTenant !== '' ? (string) $userTenant : null;
    }
}
