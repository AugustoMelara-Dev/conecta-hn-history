<?php

namespace App\Http\Controllers\Auth;

use App\Events\Security\NewLoginDetected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
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
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Dispatch Security Alert
        event(new NewLoginDetected($user, $request->ip()));

        // Role-based redirection logic
        
        // Seller -> Tenant App Panel
        if ($user->role === 'seller') {
            $tenant = $user->tenants->first();
            
            if ($tenant) {
                // Hard reload compatible with Filament
                return Inertia::location("/app/{$tenant->slug}");
            }
            
            // Fallback: If seller has no shop, send to registration
            return Inertia::location(route('register'));
        }

        // Admin -> Global Admin Panel
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            return redirect()->intended(route('filament.admin.pages.dashboard'));
        }

        // Buyers / Default -> Directory
        return redirect()->intended(route('directory.index'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
