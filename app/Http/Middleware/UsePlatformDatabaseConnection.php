<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class UsePlatformDatabaseConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('tenancy.postgres_rls.enabled', false)
            || DB::connection()->getDriverName() !== 'pgsql') {
            return $next($request);
        }

        if (! filled(config('database.connections.pgsql_platform.username'))) {
            throw new RuntimeException('The PostgreSQL platform connection is not configured.');
        }

        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('pgsql_platform');

        try {
            return $next($request);
        } finally {
            DB::setDefaultConnection($original);
        }
    }
}
