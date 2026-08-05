<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Identity\ImpersonationGrantService;
use App\Models\ImpersonationGrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class ImpersonationController extends Controller
{
    public function leave(
        Request $request,
        ImpersonationGrantService $impersonation,
    ): RedirectResponse {
        if (! $request->session()->has(ImpersonationGrantService::SESSION_GRANT_ID)) {
            return redirect()->route('home');
        }

        try {
            $impersonation->restoreOperator($request);

            return redirect('/admin');
        } catch (Throwable) {
            $impersonation->failClosed($request);

            return redirect('/login')->withErrors([
                'system' => 'No fue posible restaurar la sesión del operador. Inicia sesión nuevamente.',
            ]);
        }
    }

    public function revoke(
        Request $request,
        ImpersonationGrant $grant,
        ImpersonationGrantService $impersonation,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isSuperAdmin(), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $impersonation->revoke(
            $actor,
            $grant,
            $request,
            (string) $validated['reason'],
        );

        return back()->with('status', 'Acceso temporal revocado.');
    }
}
