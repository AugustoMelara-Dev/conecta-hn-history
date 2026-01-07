<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\RegisterUserAction;
use App\Application\DTOs\Auth\RegisterUserData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RegisteredUserController - Thin HTTP Gateway
 *
 * REFACTORED: All business logic delegated to RegisterUserAction.
 */
class RegisteredUserController extends Controller
{
    /**
     * Reserved slugs that conflict with system routes.
     */
    private const RESERVED_SLUGS = [
        'admin', 'login', 'register', 'billing', 'api', 'dashboard',
        'profile', 'settings', 'logout', 'password', 'forgot-password',
        'reset-password', 'verify-email', 'email', 'confirm-password',
        'directorio', 'ciudad', 'categoria', 'ciudades', 'explorar',
        'registrar-negocio', 'payment', 'storage', 'css', 'js', 'fonts',
        'images', 'assets', 'public', 'static', 'uploads', 'media',
    ];

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * REFACTORED: Delegates to RegisterUserAction with proper error handling.
     * Controller responsibility: Validate, Delegate, Handle Errors, Redirect.
     */
    public function store(Request $request, RegisterUserAction $action): RedirectResponse
    {
        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'tenant_name' => 'required|string|max:255|min:2',
            'slug' => [
                'required',
                'string',
                'max:100',
                'min:3',
                'alpha_dash',
                'unique:tenants,slug',
                'not_in:' . implode(',', self::RESERVED_SLUGS),
            ],
        ], [
            'tenant_name.required' => 'El nombre de tu negocio es obligatorio.',
            'tenant_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'slug.required' => 'La URL de tu negocio es obligatoria.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, números y guiones.',
            'slug.unique' => 'Esta URL ya está siendo usada por otro negocio.',
            'slug.min' => 'La URL debe tener al menos 3 caracteres.',
            'slug.not_in' => 'Esta URL está reservada y no puede ser utilizada.',
        ]);

        try {
            // DELEGATION: All business logic lives in the Action
            $dto = RegisterUserData::fromRequest($request);
            $user = $action->execute($dto);

            Auth::login($user);

            return redirect()->intended(route('filament.admin.pages.dashboard'));

        } catch (\Exception $e) {
            // GRACEFUL DEGRADATION: Log error and return user-friendly message
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Hubo un problema inesperado al crear tu cuenta. Por favor, intenta de nuevo más tarde.']);
        }
    }
}


