<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Tenant\Contracts\OnboardingReader;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $businessTypes = [
            [
                'value' => 'appointments',
                'business_type' => BusinessType::SERVICE->value,
                'label' => 'Recibir reservas',
                'description' => 'Starter Appointments: agenda confiable, servicios claros y reservas sin choques.',
                'capabilities' => ['Servicios reservables', 'Agenda y disponibilidad', 'Personal y recordatorios'],
                'examples' => ['Clinicas', 'Barberias', 'Spa'],
                'rubro_key' => 'service-core',
                'starter_pack' => 'Starter Appointments',
            ],
            [
                'value' => 'products',
                'business_type' => BusinessType::RETAIL->value,
                'label' => 'Vender productos',
                'description' => 'Starter Products: publica oferta, activa pedidos y convierte rapido sin friccion.',
                'capabilities' => ['Oferta visible y accionable', 'Pedidos y contacto inmediato', 'Inventario y promociones'],
                'examples' => ['Farmacias', 'Tiendas', 'Restaurantes'],
                'rubro_key' => 'retail-core',
                'starter_pack' => 'Starter Products',
            ],
            [
                'value' => 'consulting_leads',
                'business_type' => BusinessType::SERVICE->value,
                'label' => 'Captar consultas',
                'description' => 'Starter Consulting: autoridad, CTA claro y seguimiento comercial desde el primer dia.',
                'capabilities' => ['Landing consultiva', 'Captura por WhatsApp o formulario', 'CRM y seguimiento'],
                'examples' => ['Abogados', 'Consultores', 'Servicios tecnicos'],
                'rubro_key' => 'consulting-core',
                'starter_pack' => 'Starter Consulting',
            ],
            [
                'value' => 'hybrid',
                'business_type' => BusinessType::SERVICE->value,
                'label' => 'Combinar flujos',
                'description' => 'Para negocios que venden productos y tambien reservan servicios. Se activa por revision.',
                'capabilities' => ['Productos', 'Servicios', 'Agenda bajo allowlist'],
                'examples' => ['Clinicas con tienda', 'Academias', 'Talleres'],
                'rubro_key' => 'hybrid-core',
                'disabled' => true,
                'badge' => 'Piloto controlado',
            ],
        ];

        return Inertia::render('Auth/RegisterTenant', [
            'categories' => $reader->getBusinessCategories(),
            'cities' => $reader->getAvailableCities(),
            'businessTypes' => $businessTypes,
            'businessTypeSuggestions' => [
                'restaurantes' => 'products',
                'tiendas' => 'products',
                'servicios' => 'appointments',
                'salud' => 'appointments',
                'abogados' => 'consulting_leads',
                'consultorias' => 'consulting_leads',
            ],
            'segmentProfiles' => [
                [
                    'key' => 'retail-core',
                    'label' => 'Venta de productos',
                    'commercialMode' => 'products',
                    'businessType' => BusinessType::RETAIL->value,
                    'categories' => ['restaurantes', 'tiendas'],
                    'teaser' => 'Prioriza oferta visible, precios y canal de pedido.',
                    'onboardingTitle' => 'Starter Products: vende y cobra mas rapido',
                    'onboardingCopy' => 'Publica oferta accionable, valida pedidos y despues escala promociones y cobros.',
                    'modules' => ['Productos', 'Pedidos', 'Cupones', 'Marketing'],
                ],
                [
                    'key' => 'service-core',
                    'label' => 'Reservas y agenda',
                    'commercialMode' => 'appointments',
                    'businessType' => BusinessType::SERVICE->value,
                    'categories' => ['servicios', 'salud'],
                    'teaser' => 'Prioriza agenda y disponibilidad de atencion.',
                    'onboardingTitle' => 'Starter Appointments: agenda confiable y sin friccion',
                    'onboardingCopy' => 'Define servicios y disponibilidad real antes de abrir citas y recordatorios.',
                    'modules' => ['Servicios', 'Agenda', 'Personal', 'Recordatorios'],
                ],
                [
                    'key' => 'consulting-core',
                    'label' => 'Consultas profesionales',
                    'commercialMode' => 'consulting_leads',
                    'businessType' => BusinessType::SERVICE->value,
                    'categories' => ['servicios', 'salud', 'abogados', 'consultorias'],
                    'teaser' => 'Prioriza confianza, CTA y seguimiento comercial.',
                    'onboardingTitle' => 'Starter Consulting: convierte visitas en consultas calificadas',
                    'onboardingCopy' => 'Explica propuesta, activa CTA principal y opera seguimiento comercial sin parecer tienda.',
                    'modules' => ['Landing', 'Leads', 'CRM', 'WhatsApp'],
                ],
            ],
            'settings' => [
                'freeProductLimit' => (int) config('conecta.free_plan_product_limit', 10),
                'proProductLimit' => (int) config('conecta.pro_plan_product_limit', 100),
                'freePlanPrice' => 0,
                'proPlanPrice' => (float) config('conecta.pro_plan_price', 500.00),
                'directoryHost' => config('app.domain'),
                'registrationHost' => request()->getHost(),
                'requestHost' => request()->getHost(),
                'slugCheckUrl' => route('api.check-slug', absolute: false),
                'legalVersions' => [
                    'terms' => '2026-05-10',
                    'privacy' => '2026-05-10',
                ],
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
    ): RedirectResponse|HttpResponse {
        try {
            // PRE-PROCESS: File upload handled in Controller (Infrastructure Layer)
            $logoPath = $request->file('logo')?->store('tenants/logos', 'public');
            $commercialMode = CommercialMode::from((string) $request->validated('commercial_mode'));
            $businessType = $commercialMode->businessType();

            if ($commercialMode === CommercialMode::HYBRID && ! (bool) config('conecta.hybrid_commercial_mode_enabled', false)) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['commercial_mode' => 'El modo mixto esta disponible solo por piloto controlado.']);
            }

            // DELEGATION: Command Bus
            $command = new \App\Application\Tenant\Commands\RegisterTenantCommand(
                businessName: (string) $request->validated('business_name'),
                slug: \Illuminate\Support\Str::slug((string) $request->validated('slug')),
                ownerName: (string) $request->validated('name'),
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
                cityId: (int) $request->validated('city_id'),
                categoryId: $request->filled('category_id') ? (int) $request->validated('category_id') : null,
                whatsapp: $request->validated('whatsapp'),
                selectedPlan: (string) $request->validated('selected_plan'),
                logoPath: $logoPath,
                businessType: $businessType->value,
                commercialMode: $commercialMode->value,
                rubroKey: $request->input('rubro_key')
            );

            // EXECUTE
            $result = $bus->dispatch($command);
            $user = $this->resolveRegisteredUser($result, (string) $request->validated('email'));

            if (! $user instanceof User) {
                return redirect()
                    ->route('login')
                    ->with('warning', 'Tu negocio se creo, pero debes iniciar sesion para continuar al panel.');
            }

            Auth::login($user);

            Log::info('owner_registration_contract_applied', [
                'user_id' => (string) $user->id,
                'business_type' => $businessType->value,
                'commercial_mode' => $commercialMode->value,
                'rubro_key' => $request->input('rubro_key'),
                'selected_plan' => (string) $request->validated('selected_plan'),
                'terms_accepted' => (bool) $request->boolean('terms_accepted'),
                'privacy_accepted' => (bool) $request->boolean('privacy_accepted'),
                'terms_version' => (string) $request->input('terms_version', ''),
                'privacy_version' => (string) $request->input('privacy_version', ''),
            ]);

            $panelUrl = $panelUrlResolver->forUser($user);
            $registerUrl = route('tenant.register', absolute: false);

            if ($panelUrl === $registerUrl || str_contains($panelUrl, '/registrar-negocio')) {
                $tenantSlug = Str::slug((string) $request->validated('slug'));
                $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

                if ($tenant instanceof Tenant) {
                    $user->forceFill(['tenant_id' => $tenant->id])->save();
                    $panelUrl = "/app/{$tenant->slug}";
                }
            }

            $normalizedPanelUrl = $panelUrl;
            if (str_starts_with($panelUrl, 'http://') || str_starts_with($panelUrl, 'https://')) {
                $normalizedPanelUrl = parse_url($panelUrl, PHP_URL_PATH) ?: $panelUrl;
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location($normalizedPanelUrl);
            }

            return redirect()->to($normalizedPanelUrl);

        } catch (\Exception $e) {
            Log::error('tenant_registration_failed', [
                'email' => $request->validated('email'),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Error: '.$e->getMessage()]);
        }
    }

    private function resolveRegisteredUser(mixed $result, string $email): ?User
    {
        if (is_array($result) && isset($result['user']) && $result['user'] instanceof User) {
            return $result['user'];
        }

        return User::query()->where('email', $email)->first();
    }
}
