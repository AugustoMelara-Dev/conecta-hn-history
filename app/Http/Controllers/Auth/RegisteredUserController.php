<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RegisteredUserController - Atomic Onboarding
 *
 * Implements transactional User + Tenant creation.
 * CRITICAL: Never leaves orphan users without a tenant.
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
     * ATOMIC OPERATION:
     * 1. Validate user + tenant data
     * 2. Begin transaction
     * 3. Create User
     * 4. Create Tenant linked to User
     * 5. Commit or Rollback
     * 6. Redirect to admin panel (not generic dashboard)
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // ═══════════════════════════════════════════════════════════════
        // EXTENDED VALIDATION: User + Tenant data
        // ═══════════════════════════════════════════════════════════════
        $validated = $request->validate([
            // User fields
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Tenant fields (New for atomic onboarding)
            'tenant_name' => 'required|string|max:255|min:2',
            'slug' => [
                'required',
                'string',
                'max:100',
                'min:3',
                'alpha_dash',  // Only alphanumeric, dashes, underscores
                'unique:tenants,slug',  // Must be unique
                'not_in:'.implode(',', self::RESERVED_SLUGS),  // Blacklist
            ],
        ], [
            // Custom error messages in Spanish
            'tenant_name.required' => 'El nombre de tu negocio es obligatorio.',
            'tenant_name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'slug.required' => 'La URL de tu negocio es obligatoria.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, números y guiones.',
            'slug.unique' => 'Esta URL ya está siendo usada por otro negocio.',
            'slug.min' => 'La URL debe tener al menos 3 caracteres.',
            'slug.not_in' => 'Esta URL está reservada y no puede ser utilizada.',
        ]);

        // ═══════════════════════════════════════════════════════════════
        // ATOMIC TRANSACTION: User + Tenant
        // If either fails, both are rolled back
        // ═══════════════════════════════════════════════════════════════
        $user = DB::transaction(function () use ($validated) {
            // Step 1: Create User
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Step 2: Create Tenant linked to User
            Tenant::create([
                'name' => $validated['tenant_name'],
                'slug' => Str::lower($validated['slug']),
                'user_id' => $user->id,

                // ZERO-CONFIG DEFAULTS: Ready to use immediately
                'plan_type' => 'semilla',
                'subscription_status' => 'active',
                'brand_color' => '#2563EB',  // Corporate blue
                'is_active' => true,
                'product_limit' => 12,  // Semilla limit
            ]);

            return $user;
        });

        // ═══════════════════════════════════════════════════════════════
        // POST-REGISTRATION: Events and Login
        // ═══════════════════════════════════════════════════════════════
        event(new Registered($user));
        Auth::login($user);

        // ═══════════════════════════════════════════════════════════════
        // CONTEXTUAL REDIRECT: To admin panel, not generic dashboard
        // This provides immediate "Wow" moment
        // ═══════════════════════════════════════════════════════════════
        return redirect()->intended(route('filament.admin.pages.dashboard'));
    }
}
