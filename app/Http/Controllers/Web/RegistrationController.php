<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Infrastructure\Tenant\Policies\SlugPolicy;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * RegistrationController - React/Inertia Tenant Registration
 *
 * MIGRATED FROM: App\Livewire\Pages\RegisterTenant
 * REFACTORED: Now uses Repository interfaces instead of Eloquent models
 */
final class RegistrationController extends Controller
{
    public function __construct(
        private readonly CommandBusInterface $bus,
        private readonly ReferenceDataReaderInterface $referenceDataReader,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly StatefulGuard $guard,
    ) {}

    /**
     * Display the registration form.
     */
    public function create(): Response|SymfonyResponse
    {
        // Redirect if already authenticated
        if ($this->guard->check()) {
            return Inertia::location('/app');
        }

        return Inertia::render('Auth/RegisterTenant', [
            'cities' => $this->referenceDataReader->getAvailableCities(),
            'categories' => $this->referenceDataReader->getActiveCategories(),
            'settings' => [
                'freeProductLimit' => config('conecta.free_plan_product_limit', 10),
                'proProductLimit' => config('conecta.pro_plan_product_limit', 100),
                'proPlanPrice' => config('conecta.pro_plan_price', 500.00),
            ],
        ]);
    }

    /**
     * Check slug availability (API endpoint for real-time validation).
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|alpha_dash|min:3|max:50',
        ]);

        $slug = Str::slug($request->input('slug'));

        // Check reserved slugs
        if (SlugPolicy::isReservedSlug($slug)) {
            return response()->json([
                'available' => false,
                'message' => 'Esta dirección está reservada por el sistema.',
            ]);
        }

        // Check database via Repository (not direct Eloquent)
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
            'message' => '¡Esta dirección está disponible!',
        ]);
    }

    /**
     * Process the registration.
     */
    public function store(RegisterTenantRequest $request)
    {
        // Resolve optional reference fields: default to first available if not submitted by the form
        $cityId = (int) ($request->validated('city_id')
            ?? \App\Models\City::value('id')
            ?? 1);
        $categoryId = (int) ($request->validated('category_id')
            ?? \App\Models\Category::value('id')
            ?? 1);

        // Create command from validated request
        $command = new \App\Application\Tenant\Commands\RegisterTenantCommand(
            businessName: $request->validated('business_name'),
            slug: Str::slug($request->validated('slug')),
            ownerName: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            cityId: $cityId,
            categoryId: $categoryId,
            whatsapp: $request->validated('whatsapp'),
            selectedPlan: $request->validated('selected_plan', 'free'),
        );

        // Dispatch via Command Bus
        /** @var \App\Application\Tenant\Results\RegisterTenantResult $result */
        $result = $this->bus->dispatch($command);

        // Look up the persisted User to log them in
        $user = \App\Models\User::where('email', $result->ownerEmail)->firstOrFail();
        $this->guard->login($user);

        return redirect()
            ->route('dashboard')
            ->with('success', '¡Bienvenido a Conecta HN! Tu negocio está activo.');
    }
}
