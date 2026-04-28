<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\ApiLifecycleHeaderFactory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiLifecycleHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $version): Response
    {
        $response = $next($request);

        return ApiLifecycleHeaderFactory::apply($request, $response, $version);
    }
}
