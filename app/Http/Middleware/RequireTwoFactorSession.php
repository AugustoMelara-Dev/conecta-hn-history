<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireTwoFactorSession
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $attributes = $user->getAttributes();
        if (! array_key_exists('two_factor_secret', $attributes)
            || ! array_key_exists('two_factor_confirmed_at', $attributes)) {
            $user->refresh();
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            if ((bool) config('security.admin_mfa_required', false) && $user->isSuperAdmin()) {
                return redirect()->route('profile.two-factor.show')->withErrors([
                    'two_factor' => 'Configura y confirma el segundo factor antes de abrir herramientas de plataforma.',
                ]);
            }

            return $next($request);
        }

        $verification = $request->session()->get('auth.two_factor_verified');
        $maxAgeSeconds = max(60, (int) config('session.lifetime', 120) * 60);
        $valid = is_array($verification)
            && isset($verification['user_id'], $verification['verified_at'])
            && hash_equals((string) $user->getAuthIdentifier(), (string) $verification['user_id'])
            && is_numeric($verification['verified_at'])
            && (int) $verification['verified_at'] >= now()->getTimestamp() - $maxAgeSeconds;

        if ($valid) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Completa el segundo factor para abrir el panel.',
        ]);
    }
}
