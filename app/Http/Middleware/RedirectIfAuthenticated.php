<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
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
                    return redirect('/app');
                }

                if ($user->role === 'buyer') {
                    return redirect('/mi-cuenta');
                }

                return redirect(route('directory.index'));
            }
        }

        return $next($request);
    }
}
