<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HydrateLocationFromCookie
{
    /**
     * Handle an incoming request.
     *
     * SECURITY DIRECTIVE 2: Sticky Persistence
     * Hydrate session from cookie if session is empty
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If session doesn't have city but cookie does, restore it
        if (! session()->has('active_city_id') && $request->cookie('conecta_city_id')) {
            session(['active_city_id' => $request->cookie('conecta_city_id')]);
        }

        return $next($request);
    }
}
