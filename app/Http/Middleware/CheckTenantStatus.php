<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if ($tenant) {
            // If user is the owner, allow access
            if ($request->user() && $request->user()->id === $tenant->user_id) {
                return $next($request);
            }

            // If tenant is not approved, abort
            if ($tenant->status !== 'approved') {
                abort(404);
            }
        }

        return $next($request);
    }
}
