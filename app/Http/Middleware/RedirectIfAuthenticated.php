<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Panel\CanonicalPanelUrlResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function __construct(
        private readonly CanonicalPanelUrlResolver $panelUrlResolver
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::user();

                if ($user->role === 'super_admin') {
                    return redirect('/admin');
                }

                if ($user->role === 'seller') {
                    return redirect($this->panelUrlResolver->forUser($user));
                }

                if ($user->role === 'buyer') {
                    return redirect('/mi-cuenta');
                }

                return redirect($this->panelUrlResolver->forUser($user));
            }
        }

        return $next($request);
    }
}
