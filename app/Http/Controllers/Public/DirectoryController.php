<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Contracts\DirectoryReader;
use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Domain\Directory\DTOs\DirectorySearchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantCardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DirectoryController - Hexagonal Compliant (DIP Remediation)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero App\Models imports.
 * - Route Model Binding replaced with scalar + Reader lookup.
 * - All entity resolution via DirectoryReader contract.
 */
final class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryReader $reader,
        private readonly PublicTenantEngagementGatewayInterface $engagement,
    ) {}

    public function index(Request $request): Response
    {
        // 1. Adapter: Convert HTTP Request to Domain DTO
        $filters = DirectorySearchData::fromArray($request->all());

        // 2. Application/Domain Query via Reader (No Cache/Eloquent here)
        $tenants = $this->reader->searchTenants($filters);

        // 3. Get auxiliary data via Reader (decoupled)
        $cities = $this->reader->getCitiesWithTenants();
        $activeCity = null;
        if ($filters->citySlug && $filters->citySlug !== 'all') {
            // Use pre-loaded cities collection instead of direct Eloquent
            $activeCity = $cities->firstWhere('slug', $filters->citySlug);
        }

        $categories = $this->reader->getCategoriesWithTenants();

        // 4. Transform using Resource (Clean Presentation)
        $tenantIds = collect($tenants->items())
            ->map(static fn ($tenant): string => (string) $tenant->getKey())
            ->all();
        $favoriteIds = $request->user()
            ? $this->engagement->favoriteTenantIds((string) $request->user()->getKey(), $tenantIds)
            : [];

        $tenants->through(function ($tenant) use ($favoriteIds, $request): array {
            $tenant->setAttribute('is_favorited', in_array((string) $tenant->getKey(), $favoriteIds, true));

            return (new TenantCardResource($tenant))->resolve($request);
        });

        return Inertia::render('Directory/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'category', 'city']),
            'activeCity' => $activeCity,
            'categories' => $categories,
            'cities' => $cities,
        ]);
    }

    /**
     * Filter by city using scalar slug (DIP Compliant).
     *
     * Route Model Binding replaced with explicit lookup via Reader.
     */
    public function byCity(string $citySlug, Request $request): Response
    {
        // Validate city exists via Reader (throws 404 if not found)
        $city = $this->reader->findCityBySlug($citySlug);

        if (! $city) {
            abort(404, 'Ciudad no encontrada.');
        }

        $request->merge(['city' => $city->slug]);

        return $this->index($request);
    }

    /**
     * Filter by category using scalar slug (DIP Compliant).
     *
     * Route Model Binding replaced with explicit lookup via Reader.
     */
    public function byCategory(string $categorySlug, Request $request): Response
    {
        // Validate category exists via Reader (throws 404 if not found)
        $category = $this->reader->findCategoryBySlug($categorySlug);

        if (! $category) {
            abort(404, 'Categoría no encontrada.');
        }

        $request->merge(['category' => $category->slug]);

        return $this->index($request);
    }
}
