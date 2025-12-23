<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DirectoryController - Faceted Search with Data Transformation
 *
 * Responsabilidad: Manejar búsqueda facetada y transformar datos para
 * que coincidan con el contrato de interfaz de Directory/Index.jsx
 */
class DirectoryController extends Controller
{
    /**
     * Display the business directory with search, filters and pagination.
     *
     * SECURITY HARDENING:
     * 1. Input validation before database operations
     * 2. All SQL encapsulated in Model scopes
     * 3. No raw SQL in controller
     */
    public function index(Request $request): Response
    {
        // ═══════════════════════════════════════════════════════════════
        // INPUT VALIDATION: Sanitize and validate all query parameters
        // Prevents type confusion attacks and SQL injection vectors
        // ═══════════════════════════════════════════════════════════════
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100', 'exists:categories,slug'],
            'city' => ['nullable', 'string', 'max:100', 'exists:cities,slug'],
        ], [
            'search.max' => 'La búsqueda no puede exceder 100 caracteres.',
            'category.exists' => 'La categoría seleccionada no es válida.',
            'city.exists' => 'La ciudad seleccionada no es válida.',
        ]);

        // ═══════════════════════════════════════════════════════════════
        // QUERY BUILDER: Clean, semantic, and secure
        // All SQL logic encapsulated in Model scopes
        // ═══════════════════════════════════════════════════════════════
        $tenants = Tenant::query()
            ->published()             // Scope: is_active + subscription_status = active
            ->search($validated['search'] ?? null)           // Scope: LIKE search
            ->inCategory($validated['category'] ?? null)     // Scope: category filter
            ->inCity($validated['city'] ?? null)             // Scope: city filter
            ->inPriorityOrder()       // Scope: Enterprise > Pro > Semilla
            ->inRandomOrder()         // Fair exposure within tier
            ->with(['city:id,name,slug', 'category:id,name,slug,icon'])
            ->paginate(12)
            ->withQueryString();

        // Transform each tenant to match frontend contract
        $tenants->through(function (Tenant $tenant) {
            return [
                // Core fields
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'description' => $tenant->description ?? 'Descubre lo que tenemos para ti.',

                // Images: Use hero_image if available, fallback to banner/cover
                'banner_path' => $tenant->hero_image ?? $tenant->banner_path ?? $tenant->cover_path,
                'blur_hash' => $tenant->blur_hash,
                'brand_color' => $tenant->brand_color ?? '#2563EB',

                // Status: Use computed is_pro from accessor
                'is_pro' => $tenant->is_pro,
                'plan_type' => $tenant->plan_type ?? 'semilla',

                // Relations as objects
                'city' => $tenant->city ? [
                    'id' => $tenant->city->id,
                    'name' => $tenant->city->name,
                    'slug' => $tenant->city->slug,
                ] : ['id' => 0, 'name' => 'Honduras', 'slug' => 'honduras'],

                'category' => $tenant->category ? [
                    'id' => $tenant->category->id,
                    'name' => $tenant->category->name,
                    'slug' => $tenant->category->slug,
                    'icon' => $tenant->category->icon ?? '🏪',
                ] : ['id' => 0, 'name' => 'General', 'slug' => 'general', 'icon' => '🏪'],
            ];
        });

        // Get active city for page title
        $activeCity = null;
        if ($request->filled('city')) {
            $activeCity = City::where('slug', $request->input('city'))->first(['id', 'name', 'slug']);
        }

        return Inertia::render('Directory/Index', [
            // Paginated tenants (Laravel paginator object)
            'tenants' => $tenants,

            // Current filter state for input persistence
            'filters' => $request->only(['search', 'category', 'city']),

            // Active city for page title
            'activeCity' => $activeCity,

            // Dropdown options
            'categories' => Category::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'slug', 'icon'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon ?? '🏪',
                ]),

            'cities' => City::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Handle SEO routes for cities (/ciudad/{city})
     */
    public function byCity(City $city, Request $request): Response
    {
        $request->merge(['city' => $city->slug]);

        return $this->index($request);
    }

    /**
     * Handle SEO routes for categories (/categoria/{category})
     */
    public function byCategory(Category $category, Request $request): Response
    {
        $request->merge(['category' => $category->slug]);

        return $this->index($request);
    }
}
