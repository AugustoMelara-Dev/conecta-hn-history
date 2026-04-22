<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Auth\AuthRedirector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePublicSurfaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (AuthRedirector::isDirectorySurfaceUser($user)) {
            return $next($request);
        }

        return redirect()->to(AuthRedirector::destinationFor($user));
    }
}
