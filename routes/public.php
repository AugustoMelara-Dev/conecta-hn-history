<?php

use App\Http\Controllers\Public\ExploreController;
use App\Http\Controllers\Public\WelcomeController;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes - Inertia/React Pages (Guest Access)
|--------------------------------------------------------------------------
|
| Routes for public-facing pages. SEO optimized.
| These routes do NOT require authentication.
|
| ROUTING ARCHITECTURE (Luxury Entrance Strategy):
| - `/` → B2B Landing Page (Welcome.jsx) - Sales focus
| - `/explore` → Marketplace/Directory (Public/Home.jsx) - Discovery
| - `/@{slug}` → Tenant Microsite - Safe namespace, no collisions
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// HOME - B2B Landing Page (Sales Engine)
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/', WelcomeController::class)->name('home');

// ═══════════════════════════════════════════════════════════════════════════
// EXPLORE - Marketplace Discovery (Featured Tenants)
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/explore', ExploreController::class)->name('public.explore');

// ═══════════════════════════════════════════════════════════════════════════
// DIRECTORY - Search Engine (Full Filtering)
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/directory', function (\Illuminate\Http\Request $request) {
    $search = $request->string('search')->trim()->toString();
    $city = $request->string('city')->toString();
    $category = $request->string('category')->toString();
    $sort = $request->string('sort', 'newest')->toString();

    $tenantColumns = collect([
        'id', 'name', 'slug', 'description', 'cover_path', 'hero_image', 'banner_path',
        'logo_path', 'city_id', 'category_id', 'is_visible', 'created_at',
    ])->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))->values()->all();

    $query = Tenant::query()
        ->select($tenantColumns)
        ->when(Schema::hasColumn('tenants', 'is_visible'), fn ($q) => $q->where('is_visible', true))
        ->when($search && Schema::hasColumn('tenants', 'name'), fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
        ->when($city && Schema::hasColumn('tenants', 'city_id'), fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $city)))
        ->when($category && Schema::hasColumn('tenants', 'category_id'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)));

    // Sorting
    $query = match ($sort) {
        'popular' => $query->withCount('orders')->orderByDesc('orders_count'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $tenants = $query->paginate(12)->through(function (Tenant $t): array {
        $attributes = $t->getAttributes();
        $coverPath = $attributes['cover_path'] ?? $attributes['hero_image'] ?? $attributes['banner_path'] ?? null;
        $logoPath = $attributes['logo_path'] ?? null;

        return [
            'id' => $attributes['id'] ?? $t->id,
            'name' => $attributes['name'] ?? 'Negocio local',
            'slug' => $attributes['slug'] ?? '',
            'cover_url' => $coverPath ? Storage::url($coverPath) : null,
            'logo_url' => $logoPath ? Storage::url($logoPath) : null,
            'category' => $t->relationLoaded('category') ? $t->category?->name : null,
            'city' => $t->relationLoaded('city') ? $t->city?->name : null,
            'description' => \Illuminate\Support\Str::limit($attributes['description'] ?? '', 80),
            'is_open' => method_exists($t, 'isCurrentlyOpen') ? $t->isCurrentlyOpen() : true,
        ];
    });

    return Inertia::render('Public/Directory/Index', [
        'tenants' => $tenants->items(),
        'filters' => [
            'search' => $search,
            'city' => $city,
            'category' => $category,
            'sort' => $sort,
        ],
        'cities' => City::has('tenants')->orderBy('name')->get(['id', 'name', 'slug']),
        'categories' => Category::has('tenants')->orderBy('name')->get(['id', 'name', 'slug']),
        'meta' => [
            'total' => $tenants->total(),
            'current_page' => $tenants->currentPage(),
            'last_page' => $tenants->lastPage(),
        ],
    ]);
})->name('directory.index');

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SUBDOMAIN ROUTES
// ═══════════════════════════════════════════════════════════════════════════
// Moved to routes/web.php before the central `/` route so the domain-specific
// microsite route wins over the root landing route.
// ═══════════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // TENANT MICROSITE - Public Profile (CQRS: READ-ONLY Controller)
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
    // BOOKING WIZARD - Public Appointment Booking (SERVICE-type tenants)
    // ═══════════════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════════════════
// GUEST APPOINTMENT PORTAL - Signed URL Access (No Auth Required)
// These are domain-agnostic since they use signed URLs
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/appointments/{appointment}/manage', [\App\Http\Controllers\Public\AppointmentManagementController::class, 'show'])
    ->name('public.appointment.manage')
    ->middleware('signed');

Route::post('/appointments/{appointment}/cancel', [\App\Http\Controllers\Public\AppointmentManagementController::class, 'cancel'])
    ->name('public.appointment.cancel')
    ->middleware('signed');
