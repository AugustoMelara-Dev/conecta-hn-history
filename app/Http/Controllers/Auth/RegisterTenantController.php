<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Tenant\Contracts\OnboardingReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RegisterTenantController - Thin HTTP Gateway
 *
 * REFACTORED:
 * - Business Logic -> RegisterTenantAction
 * - Data Retrieval -> OnboardingReader
 */
final class RegisterTenantController extends Controller
{
    /**
     * Display the tenant registration form.
     */
    public function create(OnboardingReader $reader): Response
    {
        return Inertia::render('Auth/RegisterTenant', [
            'categories' => $reader->getBusinessCategories(),
            'cities' => $reader->getAvailableCities(),
            'businessTypes' => [
                [
                    'value' => 'products',
                    'label' => 'Vender productos',
                    'description' => 'Catalogo, inventario y pedidos como ruta principal.',
                    'business_type' => 'retail',
                    'rubro_key' => 'products',
                ],
                [
                    'value' => 'appointments',
                    'label' => 'Recibir reservas',
                    'description' => 'Servicios, agenda y citas como ruta principal.',
                    'business_type' => 'service',
                    'rubro_key' => 'appointments',
                ],
                [
                    'value' => 'consulting_leads',
                    'label' => 'Captar prospectos',
                    'description' => 'Consultas calificadas y seguimiento comercial.',
                    'business_type' => 'service',
                    'rubro_key' => 'consulting',
                ],
            ],
            'settings' => [
                'freeProductLimit' => config('conecta.free_plan_product_limit', 10),
                'proProductLimit' => config('conecta.pro_plan_product_limit', 100),
                'proPlanPrice' => config('conecta.pro_plan_price', 500.00),
                'registrationHost' => request()->getHost(),
                'directoryHost' => config('app.domain', 'localhost'),
            ],
        ]);
    }

    /**
     * Handle a new tenant registration request.
     *
     * REFACTORED: Delegates to RegisterTenantAction.
     * Controller responsibility: Validate, Delegate, Redirect/Error.
     */
    public function store(
        RegisterTenantRequest $request,
        \App\Application\Shared\Contracts\CommandBusInterface $bus,
        CanonicalPanelUrlResolver $panelUrlResolver
    ): RedirectResponse {
        try {
            // PRE-PROCESS: File upload handled in Controller (Infrastructure Layer)
            $logoPath = $request->file('logo')?->store('tenants/logos', 'public');
            $commercialMode = (string) $request->validated('commercial_mode');
            $businessType = $request->validated('business_type')
                ?: match ($commercialMode) {
                    'appointments', 'consulting_leads' => 'service',
                    default => 'retail',
                };

            // DELEGATION: Command Bus
            $command = new \App\Application\Tenant\Commands\RegisterTenantCommand(
                businessName: $request->validated('business_name'),
                slug: \Illuminate\Support\Str::slug($request->validated('slug')),
                ownerName: $request->validated('name'),
                email: $request->validated('email'),
                password: $request->validated('password'),
                cityId: (int) $request->validated('city_id'),
                categoryId: $request->validated('category_id') ? (int) $request->validated('category_id') : null,
                whatsapp: $request->validated('whatsapp'),
                commercialMode: $commercialMode,
                businessType: $businessType,
                rubroKey: $request->validated('rubro_key'),
                selectedPlan: $request->validated('selected_plan', 'free'),
                logoPath: $logoPath
            );

            // EXECUTE
            $result = $bus->dispatch($command);
            $user = User::where('email', $result->ownerEmail)->firstOrFail();

            Auth::login($user);

            return redirect()->intended($panelUrlResolver->forUser($user));

        } catch (\Exception $e) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Error: '.$e->getMessage()]);
        }
    }
}
