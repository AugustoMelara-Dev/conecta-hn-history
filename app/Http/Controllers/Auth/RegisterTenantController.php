<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\RegisterTenantAction;
use App\Application\DTOs\Auth\RegisterTenantData;
use App\Domain\Tenant\Contracts\OnboardingReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RegisterTenantController - Thin HTTP Gateway
 *
 * REFACTORED:
 * - Business Logic -> RegisterTenantAction
 * - Data Retrieval -> OnboardingReader
 */
class RegisterTenantController extends Controller
{
    /**
     * Display the tenant registration form.
     */
    public function create(OnboardingReader $reader): Response
    {
        return Inertia::render('Auth/RegisterTenant', [
            'categories' => $reader->getBusinessCategories(),
            'cities' => $reader->getAvailableCities(),
        ]);
    }

    /**
     * Handle a new tenant registration request.
     *
     * REFACTORED: Delegates to RegisterTenantAction.
     * Controller responsibility: Validate, Delegate, Redirect/Error.
     */
    public function store(Request $request, RegisterTenantAction $action): RedirectResponse
    {
        // Validation
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        try {
            // DELEGATION: All business logic lives in the Action
            $dto = RegisterTenantData::fromRequest($request);
            $result = $action->execute($dto);

            Auth::login($result['user']);

            return redirect()->intended(route('tenant.dashboard'));

        } catch (\RuntimeException $e) {
            // Handle semantic errors from Action
            if (str_starts_with($e->getMessage(), 'EMAIL_COLLISION')) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['email' => 'Este correo electrónico ya está registrado.']);
            }

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'El sistema está experimentando alta demanda. Intenta de nuevo.']);
        }
    }
}
