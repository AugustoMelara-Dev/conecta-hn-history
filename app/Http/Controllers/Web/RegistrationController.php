<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Infrastructure\Tenant\Policies\SlugPolicy;
use App\Support\Auth\AuthRedirector;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $bus,
        private readonly ReferenceDataReaderInterface $referenceDataReader,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly StatefulGuard $guard,
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

        return Inertia::render('Auth/RegisterTenant', [
            'cities' => $this->referenceDataReader->getAvailableCities(),
            'categories' => $this->referenceDataReader->getActiveCategories(),
            'businessTypes' => $this->businessTypes(),
            'businessTypeSuggestions' => $this->businessTypeSuggestions(),
            'segmentProfiles' => $this->segmentProfiles(),
            'settings' => [
                'freeProductLimit' => config('conecta.free_plan_product_limit', 10),
                'proProductLimit' => config('conecta.pro_plan_product_limit', 100),
                'proPlanPrice' => config('conecta.pro_plan_price', 500.00),
                'slugBaseHost' => request()->getHost(),
                'slugCheckUrl' => route('api.check-slug'),
                'requestHost' => request()->header('x-forwarded-host', request()->getHost()),
                'registrationHost' => $request->getHost(),
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
            whatsapp: $request->validated('whatsapp'),
            selectedPlan: $request->validated('selected_plan', 'free'),
            logoPath: $logoPath,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        );

        /** @var \App\Application\Tenant\Results\RegisterTenantResult $result */
        $result = $this->bus->dispatch($command);

        $user = \App\Models\User::where('email', $result->ownerEmail)->firstOrFail();
        $this->guard->login($user);

        return redirect()
            ->to(AuthRedirector::destinationFor($user))
            ->with('success', 'Bienvenido a Conecta HN. Tu negocio ya quedó activo.');
    }

    /**
     * @return list<array{value: string, label: string, description: string, examples: list<string>, capabilities: list<string>}>
     */
    private function businessTypes(): array
    {
        return collect(config('business-types.options', []))
            ->map(
                static fn (array $definition, string $value): array => [
                    'value' => $value,
                    'label' => (string) ($definition['label'] ?? Str::of($value)->replace('_', ' ')->title()),
                    'description' => (string) ($definition['description'] ?? ''),
                    'examples' => array_values((array) ($definition['examples'] ?? [])),
                    'capabilities' => array_values((array) ($definition['capabilities'] ?? [])),
                ]
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function businessTypeSuggestions(): array
    {
        /** @var Collection<string, array<string, mixed>> $options */
        $options = collect(config('business-types.options', []));

        return $options
            ->flatMap(function (array $definition, string $value): array {
                return collect((array) ($definition['suggested_categories'] ?? []))
                    ->mapWithKeys(static fn (string $slug): array => [$slug => $value])
                    ->all();
            })
            ->all();
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
        return collect(config('business-segments.profiles', []))
            ->map(
                static fn (array $profile, string $key): array => [
                    'key' => $key,
                    'businessType' => (string) ($profile['business_type'] ?? ''),
                    'label' => (string) ($profile['label'] ?? Str::of($key)->replace('_', ' ')->title()),
                    'teaser' => (string) ($profile['teaser'] ?? ''),
                    'onboardingTitle' => (string) ($profile['onboarding_title'] ?? 'Ruta sugerida para tu panel'),
                    'onboardingCopy' => (string) ($profile['onboarding_copy'] ?? ''),
                    'playbookTitle' => (string) ($profile['playbook_title'] ?? 'Playbook operativo'),
                    'categories' => array_values((array) ($profile['categories'] ?? [])),
                    'modules' => collect((array) ($profile['recommended_modules'] ?? []))
                        ->pluck('title')
                        ->filter(static fn (mixed $title): bool => is_string($title) && $title !== '')
                        ->values()
                        ->all(),
                ]
            )
            ->values()
            ->all();
    }
}
