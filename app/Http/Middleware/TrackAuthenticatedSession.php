<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class TrackAuthenticatedSession
{
    public function __construct(
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $this->sessions->touch($request, $user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta sesión fue revocada. Inicia sesión nuevamente.',
            ]);
        }

        return $next($request);
    }
}
