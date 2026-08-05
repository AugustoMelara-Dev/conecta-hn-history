<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CanonicalPanelUrlResolver $panelUrlResolver,
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|SymfonyResponse
    {
        $request->authenticate();

        if ($request->session()->has('login.id')) {
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();
        if ($request->user() instanceof User) {
            $this->sessions->start($request, $request->user());
        }

        if (! $request->user()?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $panelUrl = $this->panelUrlResolver->forUser($request->user());

        if ($request->header('X-Inertia') && (str_starts_with($panelUrl, '/app') || str_starts_with($panelUrl, '/admin'))) {
            return Inertia::location($panelUrl);
        }

        return redirect()->to($panelUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->sessions->markCurrentRevoked($request, $user, 'user_logout');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
