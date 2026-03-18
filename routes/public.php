<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
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
Route::get('/', function () {
    // Minimal stats for social proof - avoid heavy queries
    $stats = [
        'total_tenants' => Tenant::where('is_active', true)->count(),
        'total_cities' => City::has('tenants')->count(),
    ];

    return Inertia::render('Welcome', [
        'auth' => [
            'user' => Auth::user(),
        ],
        'stats' => $stats,
    ]);
})->name('home');

// ═══════════════════════════════════════════════════════════════════════════
// EXPLORE - Marketplace Discovery (Featured Tenants)
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/explore', function () {
    // Featured tenants (visible, with cover images)
    $featuredTenants = Tenant::query()
        ->with(['category', 'city'])
        ->where('is_active', true)
        ->whereNotNull('logo_path')
        ->latest()
        ->limit(12)
        ->get()
        ->map(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'cover_url' => $t->cover_path ? \Illuminate\Support\Facades\Storage::url($t->cover_path) : null,
            'logo_url' => $t->logo_path ? \Illuminate\Support\Facades\Storage::url($t->logo_path) : null,
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
            'total_tenants' => Tenant::where('is_active', true)->count(),
            'total_cities' => City::has('tenants')->count(),
        ],
    ]);
})->name('public.explore');

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SUBDOMAIN ROUTES - DISABLED (Path-based fallback active in web.php)
// ═══════════════════════════════════════════════════════════════════════════
// Subdomain routing disabled for local dev + Ziggy compatibility.
// Path-based fallback: /t/{tenant:slug} defined in routes/web.php.
// To restore subdomain routing for production, uncomment the block below
// and remove the path-based group from web.php.
// ═══════════════════════════════════════════════════════════════════════════

// Route::domain('{tenant}.' . config('app.domain'))->group(function () {
//     // TENANT MICROSITE - Public Profile (CQRS: READ-ONLY Controller)
//     Route::get('/', [\App\Http\Controllers\Public\TenantMicrositeController::class, 'show'])
//         ->name('tenant.microsite.show');
//
//     // BOOKING WIZARD - Public Appointment Booking (SERVICE-type tenants)
//     Route::prefix('book')->group(function () {
//         Route::get('/', [\App\Http\Controllers\Public\BookingController::class, 'index'])
//             ->name('public.booking.index');
//         Route::post('/', [\App\Http\Controllers\Public\BookingController::class, 'store'])
//             ->name('public.booking.store');
//         Route::get('/providers/{service}', [\App\Http\Controllers\Public\BookingController::class, 'getProviders'])
//             ->name('public.booking.providers');
//         Route::get('/slots', [\App\Http\Controllers\Public\BookingController::class, 'getSlots'])
//             ->name('public.booking.slots');
//         Route::get('/success', [\App\Http\Controllers\Public\BookingController::class, 'success'])
//             ->name('public.booking.success');
//     });
// });

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
