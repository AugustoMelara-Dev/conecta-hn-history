<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\RegisterUserAction;
use App\Application\DTOs\Auth\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RegisteredUserController - Thin HTTP Gateway (Clean Controller)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero inline validation (delegated to RegisterTenantRequest)
 * - Zero business logic (delegated to RegisterUserAction)
 * - Single Responsibility: HTTP translation only
 */
final class RegisteredUserController extends Controller
{
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
     * FLOW:
     * 1. RegisterTenantRequest validates input (automatic via DI)
     * 2. RegisterUserData hydrates immutable DTO
     * 3. RegisterUserAction executes business logic
     * 4. Controller handles response/redirect
     */
    public function store(
        RegisterTenantRequest $request,
        RegisterUserAction $action
    ): RedirectResponse {
        try {
            // DTO Hydration (uses validated() from FormRequest)
            $dto = RegisterUserData::fromRequest($request);

            // Delegation to Application Layer
            $user = $action->execute($dto);

            // Authentication
            Auth::login($user);

            return redirect()->intended(route('filament.admin.pages.dashboard'));

        } catch (\Exception $e) {
            // Graceful Degradation: Log and return user-friendly error
            Log::error('User registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->validated('email'),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Hubo un problema inesperado al crear tu cuenta. Por favor, intenta de nuevo más tarde.']);
        }
    }
}
