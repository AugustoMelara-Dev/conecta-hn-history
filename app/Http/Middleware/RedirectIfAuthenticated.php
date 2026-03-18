<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
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

                if (! $user instanceof User) {
                    return redirect(route('directory.index'));
                }

                if ($user->role === UserRole::SUPER_ADMIN) {
                    return redirect('/admin');
                }

                if ($user->role === UserRole::TENANT_OWNER) {
                    return redirect('/app');
                }

                if ($user->role === UserRole::CUSTOMER) {
                    return redirect('/mi-cuenta');
                }

                return redirect(route('directory.index'));
            }
        }

        return $next($request);
    }
}
