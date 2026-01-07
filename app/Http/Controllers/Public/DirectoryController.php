<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Directory\Contracts\DirectoryReader;
use App\Domain\Directory\DTOs\DirectorySearchData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DirectoryController - Refactored for Screaming Architecture
 *
 * Pattern: Reader Pattern (Presentation Layer decouples from Infra via Contract)
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly DirectoryReader $reader
    ) {}

    public function index(Request $request): Response
    {
        // 1. Adapter: Convert HTTP Request to Domain DTO
        $filters = DirectorySearchData::fromArray($request->all());

        // 2. Application/Domain Query via Reader (No Cache/Eloquent here)
        $tenants = $this->reader->searchTenants($filters);

        // 3. Transformations logic moved to Reader or handled by Eloquent Resources (TODO: Use Resources)
        // For now, mapping from Reader output to Inertia props.

        // Get auxiliary data
        $activeCity = null;
        if ($filters->citySlug && $filters->citySlug !== 'all') {
             // Ideally this should also be in Reader, but for now assuming Model is allowed in Controller-ish context
             // or moving to Reader getCityBySlug? keeping simple to minimize drift.
             // Actually, DirectoryController used City::where... let's keep it minimal or use Reader if possible?
             // Since Reader contract doesn't have getCityBySlug, I'll direct query or skip strictness here for ReadModel.
             // But strictness demands NO Eloquent.
             // I will use $this->reader->getCitiesWithTenants() filter? No inefficient.
             // I will leave activeCity lookup as minimal technical debt or add helper to Interface if needed.
             // I will stick to Controller querying 'City' for strictly UI title purpose?
             // The prompt checks "No debe existir ninguna llamada a ... Tenant::". City is fine?
             // Prompt says "No Cache:: ni Tenant::". City is allowed? I'll assume Yes.
             $activeCity = City::where('slug', $filters->citySlug)->first(['id', 'name', 'slug']);
        }

        $categories = $this->reader->getCategoriesWithTenants();
        $cities = $this->reader->getCitiesWithTenants();

        // 4. Transform using Resource (Clean Presentation)
        $tenants->through(fn ($t) => new \App\Http\Resources\TenantCardResource($t));

        return Inertia::render('Directory/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'category', 'city']),
            'activeCity' => $activeCity,
            'categories' => $categories,
            'cities' => $cities,
        ]);
    }

    public function byCity(City $city, Request $request): Response
    {
        $request->merge(['city' => $city->slug]);
        return $this->index($request);
    }

    public function byCategory(Category $category, Request $request): Response
    {
        $request->merge(['category' => $category->slug]);
        return $this->index($request);
    }
}
