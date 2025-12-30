<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\City;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Public Routes - Inertia/React Pages (Guest Access)
|--------------------------------------------------------------------------
|
| Routes for public-facing pages. SEO optimized.
| These routes do NOT require authentication.
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// HOME - Landing Page
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/', function () {
    // Featured tenants (visible, with cover images)
    $featuredTenants = Tenant::query()
        ->where('is_visible', true)
        ->whereNotNull('logo_path')
        ->latest()
        ->limit(8)
        ->get()
        ->map(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'cover_url' => $t->cover_url,
            'logo_url' => $t->logo_url,
            'category' => $t->category?->name,
            'city' => $t->city?->name,
            'description' => \Illuminate\Support\Str::limit($t->description, 80),
        ]);

    // Categories for pills
    $categories = Category::query()
        ->withCount('tenants')
        ->having('tenants_count', '>', 0)
        ->orderByDesc('tenants_count')
        ->limit(8)
        ->get(['id', 'name', 'slug', 'icon']);

    return Inertia::render('Public/Home', [
        'featuredTenants' => $featuredTenants,
        'categories' => $categories,
        'stats' => [
            'total_tenants' => Tenant::where('is_visible', true)->count(),
            'total_cities' => City::has('tenants')->count(),
        ],
    ]);
})->name('home');

// ═══════════════════════════════════════════════════════════════════════════
// DIRECTORY - Search Engine
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/directory', function (\Illuminate\Http\Request $request) {
    $search = $request->string('search')->trim()->toString();
    $city = $request->string('city')->toString();
    $category = $request->string('category')->toString();
    $sort = $request->string('sort', 'newest')->toString();

    $query = Tenant::query()
        ->where('is_visible', true)
        ->when($search, fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
        ->when($city, fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $city)))
        ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)));

    // Sorting
    $query = match ($sort) {
        'popular' => $query->withCount('orders')->orderByDesc('orders_count'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $tenants = $query->paginate(12)->through(fn (Tenant $t) => [
        'id' => $t->id,
        'name' => $t->name,
        'slug' => $t->slug,
        'cover_url' => $t->cover_url,
        'logo_url' => $t->logo_url,
        'category' => $t->category?->name,
        'city' => $t->city?->name,
        'description' => \Illuminate\Support\Str::limit($t->description, 80),
        'is_open' => $t->isCurrentlyOpen(),
    ]);

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
// TENANT MICROSITE - Public Profile
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/{tenant:slug}', function (Tenant $tenant) {
    // Ensure tenant is visible (public)
    abort_unless($tenant->is_visible, 404);

    // Get public products only
    $products = $tenant->products()
        ->where('is_visible', true)
        ->orderBy('name')
        ->get()
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'description' => $p->description,
            'image_url' => $p->image_url,
            'stock_status' => $p->stock_status,
        ]);

    // Calculate if currently open
    $now = Carbon::now();
    $dayOfWeek = strtolower($now->format('l')); // e.g., "monday"
    $schedule = $tenant->schedule ?? [];
    $todaySchedule = $schedule[$dayOfWeek] ?? null;
    $isOpen = false;

    if ($todaySchedule && ($todaySchedule['isOpen'] ?? false)) {
        $openTime = Carbon::parse($todaySchedule['open']);
        $closeTime = Carbon::parse($todaySchedule['close']);
        $isOpen = $now->between($openTime, $closeTime);
    }

    return Inertia::render('Public/Tenant/Show', [
        'tenant' => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'description' => $tenant->description,
            'about' => $tenant->about,
            'phone' => $tenant->phone,
            'whatsapp_number' => $tenant->whatsapp_number,
            'address' => $tenant->address,
            'logo_url' => $tenant->logo_url,
            'cover_url' => $tenant->cover_url,
            'category' => $tenant->category?->name,
            'city' => $tenant->city?->name,
            'instagram_url' => $tenant->instagram_url,
            'facebook_url' => $tenant->facebook_url,
        ],
        'products' => $products,
        'isOpen' => $isOpen,
        'todaySchedule' => $todaySchedule && ($todaySchedule['isOpen'] ?? false) ? [
            'open' => $todaySchedule['open'],
            'close' => $todaySchedule['close'],
        ] : null,
    ]);
})->name('tenant.show');
