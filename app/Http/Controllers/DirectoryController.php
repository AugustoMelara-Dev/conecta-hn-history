<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DirectoryController - Faceted Search with Layered Caching
 *
 * Performance Architecture:
 * 1. Cache-Aside Pattern: Query results cached for 10 minutes
 * 2. Deterministic Keys: Composite keys prevent cache collisions
 * 3. Eager Loading: Single query for tenants with relations
 */
class DirectoryController extends Controller
{
    /**
     * Cache TTL in minutes for directory listings.
     */
    private const CACHE_TTL_MINUTES = 10;

    /**
     * Display the business directory with search, filters and pagination.
     *
     * PERFORMANCE ARCHITECTURE:
     * 1. Input validation (Security)
     * 2. Cache-Aside Pattern (Performance)
     * 3. Eager Loading (N+1 Prevention)
     * 4. Data Transformation (Contract Compliance)
     */
    public function index(Request $request): Response
    {
        // ═══════════════════════════════════════════════════════════════
        // INPUT VALIDATION: Sanitize and validate all query parameters
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
        // CACHE-ASIDE PATTERN: Wrap expensive query in cache layer
        //
        // DESIGN DECISION (Trade-off Documentation):
        // The cache key does NOT include individual tenant versions.
        // This is intentional for a global directory listing:
        // - Including versions would cause excessive cache fragmentation
        // - 10-minute TTL provides acceptable freshness for discovery
        // - New tenants appear within 10 minutes (acceptable delay)
        //
        // Key Format: directory_page_{N}_cat_{slug}_city_{slug}_search_{term}
        // ═══════════════════════════════════════════════════════════════
        $page = (int) $request->input('page', 1);
        $searchTerm = $validated['search'] ?? '';
        $categorySlug = $validated['category'] ?? 'all';
        $citySlug = $validated['city'] ?? 'all';

        // Deterministic cache key (includes search hash for uniqueness)
        $cacheKey = sprintf(
            'directory_page_%d_cat_%s_city_%s_search_%s',
            $page,
            $categorySlug,
            $citySlug,
            md5($searchTerm) // Hash search term to avoid key length issues
        );

        $tenants = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($validated) {
                return Tenant::query()
                    // PROJECTION: Only fields needed for card rendering
                    ->select([
                        'id', 'name', 'slug', 'description', 'tags', 'is_active',
                        'hero_image', 'banner_path', 'cover_path', 'blur_hash',
                        'brand_color', 'plan_type', 'subscription_status', 'subscription_ends_at',
                        'city_id', 'category_id', 'created_at'
                    ])
                    ->published()
                    ->search($validated['search'] ?? null)
                    ->inCategory($validated['category'] ?? null)
                    ->inCity($validated['city'] ?? null)
                    ->inPriorityOrder()
                    // DETERMINISTIC ORDERING: Enterprise > Pro > Semilla, then by newest
                    // NOTE: inRandomOrder() was REMOVED because caching random order
                    // defeats the purpose of randomization (users see same "random" order for 10 mins)
                    ->latest('created_at')
                    // EAGER LOADING: Strict column selection for memory economy
                    ->with([
                        'city:id,name,slug',
                        'category:id,name,slug,icon'
                    ])
                    ->paginate(12)
                    ->withQueryString();
            }
        );

        // ═══════════════════════════════════════════════════════════════
        // DATA TRANSFORMATION: Map to frontend contract
        // This runs on cached data, so it's cheap
        // ═══════════════════════════════════════════════════════════════
        $tenants->through(function (Tenant $tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'description' => $tenant->description ?? 'Descubre lo que tenemos para ti.',
                'tags' => array_slice($tenant->tags ?? [], 0, 3),
                'banner_path' => $tenant->hero_image ?? $tenant->banner_path ?? $tenant->cover_path,
                'blur_hash' => $tenant->blur_hash,
                'brand_color' => $tenant->brand_color ?? '#2563EB',
                'is_pro' => $tenant->is_pro,
                'plan_type' => $tenant->plan_type ?? 'semilla',
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

        // Get active city for page title (cheap query, no caching needed)
        $activeCity = null;
        if ($request->filled('city')) {
            $activeCity = City::where('slug', $request->input('city'))->first(['id', 'name', 'slug']);
        }

        // ═══════════════════════════════════════════════════════════════
        // DROPDOWN OPTIONS: Cached separately (long TTL, rarely changes)
        // ═══════════════════════════════════════════════════════════════
        $categories = Cache::remember('directory_categories', now()->addHours(1), function () {
            return Category::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'slug', 'icon'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon ?? '🏪',
                ]);
        });

        $cities = Cache::remember('directory_cities', now()->addHours(1), function () {
            return City::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get();
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
