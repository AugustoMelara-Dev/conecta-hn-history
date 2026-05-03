<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Infrastructure\Tenant\Policies\SlugPolicy;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use App\Support\Auth\AuthRedirector;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $bus,
        private readonly ReferenceDataReaderInterface $referenceDataReader,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly StatefulGuard $guard,
        private readonly RubroManifestReaderInterface $rubroManifest,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->guard->check()) {
            $user = $this->guard->user();

            if ($user !== null && ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return redirect()->to(AuthRedirector::destinationFor($user));
        }

        $settings = $this->generalSettings();

        return Inertia::render('Auth/RegisterTenant', [
            'cities' => $this->referenceDataReader->getAvailableCities(),
            'categories' => $this->referenceDataReader->getActiveCategories(),
            'businessTypes' => $this->businessTypes(),
            'businessTypeSuggestions' => $this->businessTypeSuggestions(),
            'segmentProfiles' => $this->segmentProfiles(),
            'settings' => [
                'freeProductLimit' => (int) ($settings?->free_plan_product_limit ?? config('conecta.free_plan_product_limit', 10)),
                'proProductLimit' => (int) ($settings?->pro_plan_product_limit ?? config('conecta.pro_plan_product_limit', 100)),
                'freePlanPrice' => (float) ($settings?->free_plan_price ?? 0.00),
                'proPlanPrice' => (float) ($settings?->pro_plan_price ?? config('conecta.pro_plan_price', 499.00)),
                'proPlanOriginalPrice' => (float) ($settings?->pro_plan_original_price ?? 799.00),
                'setupFeePrice' => (float) ($settings?->setup_fee_price ?? 2500.00),
                'slugBaseHost' => $this->resolveDirectoryHost($request),
                'slugCheckUrl' => route('api.check-slug'),
                'requestHost' => request()->header('x-forwarded-host', request()->getHost()),
                'registrationHost' => $request->getHost(),
                'platformDirectoryUrl' => config('app.platform_directory_url'),
                'platformAppUrl' => config('app.platform_app_url'),
                'directoryHost' => $this->resolveDirectoryHost($request),
                'legalVersions' => [
                    'terms' => (string) config('legal.current_versions.terms'),
                    'privacy' => (string) config('legal.current_versions.privacy'),
                ],
            ],
        ]);
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|alpha_dash|min:3|max:50',
        ]);

        $slug = Str::slug($request->input('slug'));

        if (SlugPolicy::isReservedSlug($slug)) {
            return response()->json([
                'available' => false,
                'message' => 'Esta dirección está reservada por el sistema.',
            ]);
        }

        $exists = $this->tenantRepository->existsBySlug($slug);

        if ($exists) {
            return response()->json([
                'available' => false,
                'message' => 'Esta dirección ya está en uso.',
                'suggestions' => SlugPolicy::generateSuggestions($slug, 3),
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Esta dirección está disponible.',
        ]);
    }

    public function store(RegisterTenantRequest $request): RedirectResponse
    {
        $cityId = (int) $request->validated('city_id');
        $categoryId = (int) $request->validated('category_id');
        $logoPath = $request->file('logo')?->store('tenants/logos', 'public');

        $command = new \App\Application\Tenant\Commands\RegisterTenantCommand(
            businessName: $request->validated('business_name'),
            slug: Str::slug($request->validated('slug')),
            ownerName: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            cityId: $cityId,
            categoryId: $categoryId,
            businessType: $request->validated('business_type', config('business-types.default', BusinessType::RETAIL->value)),
            rubroKey: $request->validated('rubro_key'),
            whatsapp: $request->validated('whatsapp'),
            selectedPlan: $request->validated('selected_plan', 'free'),
            logoPath: $logoPath,
            claimEvidenceType: $request->validated('claim_evidence_type'),
            claimEvidenceUrl: $request->validated('claim_evidence_url'),
            claimEvidenceNotes: $request->validated('claim_evidence_notes'),
            termsAccepted: (bool) $request->validated('terms_accepted', false),
            privacyAccepted: (bool) $request->validated('privacy_accepted', false),
            termsVersion: $request->validated('terms_version', (string) config('legal.current_versions.terms')),
            privacyVersion: $request->validated('privacy_version', (string) config('legal.current_versions.privacy')),
            acceptedIpAddress: $request->ip(),
            acceptedUserAgent: $request->userAgent(),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        );

        /** @var \App\Application\Tenant\Results\RegisterTenantResult $result */
        $result = $this->bus->dispatch($command);

        $user = \App\Models\User::where('email', $result->ownerEmail)->firstOrFail();
        $this->guard->login($user);
        $this->persistInitialOnboardingSnapshot($result->tenantId, $user);

        if ($result->requiresPayment && $result->paymentId !== null) {
            return redirect()
                ->route('payment.pending', ['payment' => $result->paymentId])
                ->with('success', 'Tu negocio fue registrado. Completa el pago para activar el plan Pro.');
        }

        return redirect()
            ->to(AuthRedirector::destinationFor($user))
            ->with('success', 'Bienvenido a Vito Business OS. Tu panel está listo y tu verificación de titularidad queda en revisión.');
    }

    /**
     * @return list<array{value: string, label: string, description: string, examples: list<string>, capabilities: list<string>}>
     */
    private function businessTypes(): array
    {
        return $this->rubroManifest->registrationOptions();
    }

    /**
     * @return array<string, string>
     */
    private function businessTypeSuggestions(): array
    {
        return $this->rubroManifest->categorySuggestions();
    }

    /**
     * @return list<array{
     *     key: string,
     *     businessType: string,
     *     label: string,
     *     teaser: string,
     *     onboardingTitle: string,
     *     onboardingCopy: string,
     *     playbookTitle: string,
     *     categories: list<string>,
     *     modules: list<string>
     * }>
     */
    private function segmentProfiles(): array
    {
        return $this->rubroManifest->segmentProfiles();
    }

    private function resolveDirectoryHost(Request $request): string
    {
        $configuredDirectoryHost = $this->extractHost(config('app.platform_directory_url'));
        if (is_string($configuredDirectoryHost) && $configuredDirectoryHost !== '') {
            return $configuredDirectoryHost;
        }

        $forwardedHost = $request->header('x-forwarded-host');
        if (is_string($forwardedHost) && trim($forwardedHost) !== '') {
            return trim(explode(',', $forwardedHost)[0]);
        }

        return $request->getHost();
    }

    private function extractHost(mixed $candidate): ?string
    {
        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $hostWithoutScheme = parse_url('https://'.ltrim($candidate, '/'), PHP_URL_HOST);
        if (is_string($hostWithoutScheme) && $hostWithoutScheme !== '') {
            return $hostWithoutScheme;
        }

        return null;
    }

    private function generalSettings(): ?GeneralSettings
    {
        try {
            return app(GeneralSettings::class);
        } catch (Throwable) {
            return null;
        }
    }

    private function persistInitialOnboardingSnapshot(string $tenantId, \App\Models\User $user): void
    {
        try {
            $tenant = Tenant::query()->whereKey($tenantId)->first();

            if ($tenant instanceof Tenant) {
                $tenantContext = app(TenantContext::class);
                $shouldResetTenantContext = ! $tenantContext->isInitialized();

                if ($shouldResetTenantContext) {
                    $tenantContext->setTenant((string) $tenant->getKey());
                }

                try {
                    app(TenantOnboardingProgressService::class)->persist($tenant, $user);
                } finally {
                    if ($shouldResetTenantContext) {
                        $tenantContext->reset();
                    }
                }
            }
        } catch (Throwable $exception) {
            if (app()->runningUnitTests()) {
                throw $exception;
            }

            report($exception);
        }
    }
}
