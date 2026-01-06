<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Login - Centralized Authentication Page
 *
 * RBAC Redirect Matrix:
 * - super_admin → /super-admin
 * - panel_user (tenant owner) → /admin (Filament)
 * - regular user → intended() or /
 */
#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = true; // Default true for mobile UX

    /**
     * Validation rules
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * Custom validation messages
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico',
            'email.email' => 'Ingresa un correo válido',
            'password.required' => 'Ingresa tu contraseña',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ];
    }

    /**
     * Authenticate the user
     */
    public function authenticate()
    {
        $this->validate();

        // Rate limiting check
        $this->ensureIsNotRateLimited();

        // Attempt authentication
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Clear rate limiter on success
        RateLimiter::clear($this->throttleKey());

        // Regenerate session to prevent fixation attacks
        session()->regenerate();

        // Flash welcome message
        $user = Auth::user();
        session()->flash('toast', '¡Hola de nuevo, '.$user->name.'! 👋');

        // ═══════════════════════════════════════════════════════════════════
        // RBAC REDIRECT MATRIX (usando campo role, no Spatie)
        // ═══════════════════════════════════════════════════════════════════

        // Level 1: Super Admin → Filament Admin Panel
        if ($user->role === 'super_admin' || $user->email === 'admin@conectahn.com') {
            return redirect()->to('/admin');
        }

        // Level 2: Seller (Tenant Owner) → Dashboard del Tenant
        if ($user->role === 'seller') {
            $tenant = $user->tenants()->first();
            if ($tenant) {
                return redirect()->to('/app/'.$tenant->slug);
            }

            // Si es seller sin tenant, crear uno
            return redirect()->to('/registrar-negocio');
        }

        // Level 3: Regular User (Consumer) → Home
        return redirect()->intended('/');
    }

    /**
     * Redirect to Google OAuth
     */
    public function loginWithGoogle()
    {
        return redirect()->route('social.redirect', 'google');
    }

    /**
     * Ensure the login request is not rate limited
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    /**
     * Render the login view
     */
    public function render()
    {
        return view('livewire.auth.login')
            ->title('Iniciar Sesión | Conecta HN');
    }
}
