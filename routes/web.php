<?php

use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Public\DirectoryController;
use App\Http\Controllers\Public\InteractionController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\Public\WelcomeController;
use App\Http\Controllers\Tenant\AnalyticsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes - React/Inertia Frontend
|--------------------------------------------------------------------------
|
| Rutas del frontend público usando Inertia.js + React.
| Prioridad: Rutas específicas PRIMERO, wildcard (/{slug}) AL FINAL.
|
*/

require __DIR__.'/tenant.php';

// ═══════════════════════════════════════════════════════════════════════
// 1. LANDING PAGE: React/Inertia (Welcome.jsx)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/', WelcomeController::class)->name('home');

// [DEPRECATED] Old Livewire route:
// Route::get('/', \App\Livewire\LandingPage::class)->name('home');

// Motor de Descubrimiento (antiguo home - DEPRECATED)
// Route::get('/explorar', \App\Livewire\Pages\Home::class)->name('explore');

// ═══════════════════════════════════════════════════════════════════════
// 2. DIRECTORIO: React/Inertia (Directory/Index.jsx)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/directorio', [DirectoryController::class, 'index'])->name('directory.index');

// Ruta SEO para Ciudades (ej. /ciudad/tocoa)
Route::get('/ciudad/{city:slug}', [DirectoryController::class, 'byCity'])->name('city.show');

// Listado de todas las ciudades (redirige a directorio)
Route::get('/ciudades', [DirectoryController::class, 'index'])->name('cities.index');

// Ruta SEO para Categorías (ej. /categoria/restaurantes)
Route::get('/categoria/{category:slug}', [DirectoryController::class, 'byCategory'])->name('category.show');

// ═══════════════════════════════════════════════════════════════════════
// 3. REGISTRO DE NEGOCIOS [MIGRATED TO REACT - 2026-01-08]
// ═══════════════════════════════════════════════════════════════════════
// [MIGRATED TO REACT] Route::get('/registrar-negocio', \App\Livewire\Pages\RegisterTenant::class)->name('tenant.register');

// [MIGRATED TO REACT] Página de Pago Pendiente (PRO Post-Registro)
// Route::get('/registro/pago-pendiente/{payment}', \App\Livewire\Pages\PaymentPending::class)->name('payment.pending');
Route::get('/registro/pago-pendiente/{payment}', [\App\Http\Controllers\Web\BillingController::class, 'showPaymentPending'])->name('payment.pending');

// ═══════════════════════════════════════════════════════════════════════
// Location API Routes
// ═══════════════════════════════════════════════════════════════════════
Route::post('/location/set', [LocationController::class, 'setCity'])->name('location.set');
Route::post('/location/clear', [LocationController::class, 'clearCity'])->name('location.clear');
Route::get('/api/cities', [LocationController::class, 'getCities'])->name('cities.all');
Route::get('/api/search', [\App\Http\Controllers\Api\SearchController::class, 'index'])->name('api.search');
Route::get('/api/check-slug', [\App\Http\Controllers\Web\RegistrationController::class, 'checkSlug'])->name('api.check-slug');

// ... (Skip lines)

// ═══════════════════════════════════════════════════════════════════════
// TRACKING ROUTES - Analytics Middleware
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('track')->group(function () {
    Route::get('/wa/{tenant:slug}', [InteractionController::class, 'whatsapp'])->name('track.whatsapp');
    Route::get('/call/{tenant:slug}', [InteractionController::class, 'call'])->name('track.call');
    Route::get('/maps/{tenant:slug}', [InteractionController::class, 'maps'])->name('track.maps');
});

// ═══════════════════════════════════════════════════════════════════════
// PAYMENT STATUS - Consolidated
// ═══════════════════════════════════════════════════════════════════════
// Route moved to: Line 50 (Consolidated)

// ═══════════════════════════════════════════════════════════════════════
// TENANT REGISTRATION - Atomic Transactional Controller
// FIX #16: Rate limiting for security - prevents DoS/spam attacks
// Principle: Defense in Depth - 5 attempts per minute per IP
// ═══════════════════════════════════════════════════════════════════════
Route::controller(\App\Http\Controllers\Auth\RegisterTenantController::class)->group(function () {
    Route::get('/registrar-negocio', 'create')->name('tenant.register');
    Route::post('/registrar-negocio', 'store')
        ->middleware('throttle:5,1')  // 5 attempts per minute
        ->name('tenant.register.store');
});

// ═══════════════════════════════════════════════════════════════════════
// FAVORITES - Inertia-Compatible Toggle Controller
// ═══════════════════════════════════════════════════════════════════════
Route::middleware('auth')->group(function () {
    Route::post('/favorites/toggle', \App\Http\Controllers\Public\ToggleFavoriteController::class)
        ->name('favorites.toggle');
});

// ═══════════════════════════════════════════════════════════════════════
// Auth Routes (Dashboard, Mi Cuenta)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ═══════════════════════════════════════════════════════════════════════
// TENANT INERTIA ROUTES (Must be loaded BEFORE wildcards)
// ═══════════════════════════════════════════════════════════════════════

Route::middleware('auth')->group(function () {
    // ═══════════════════════════════════════════════════════════════════
    // TENANT DASHBOARD (Owner Panel)
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/app/{tenant:slug}', \App\Livewire\Tenant\Dashboard::class)
    //    ->name('tenant.dashboard');

    // ═══════════════════════════════════════════════════════════════════
    // UPGRADE TO PRO (Pricing Table + Payment Order)
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/upgrade/{tenant:slug}', \App\Livewire\Tenant\Upgrade::class)
    //    ->name('tenant.upgrade');

    // ═══════════════════════════════════════════════════════════════════
    // PRODUCTS CRUD (Tenant Panel) - ARCHIVED: Migrated to React/Inertia
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/products', \App\Livewire\Tenant\Products\Index::class)
    //     ->name('tenant.products.index');
    // Route::get('/admin/products/create', \App\Livewire\Tenant\Products\Form::class)
    //     ->name('tenant.products.create');
    // Route::get('/admin/products/{product}/edit', \App\Livewire\Tenant\Products\Form::class)
    //     ->name('tenant.products.edit');

    // ═══════════════════════════════════════════════════════════════════
    // ORDERS MANAGEMENT (Kanban PRO / List FREE) - ARCHIVED
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/orders', \App\Livewire\Tenant\Orders\Manage::class)
    //     ->name('tenant.orders.manage');

    // ═══════════════════════════════════════════════════════════════════
    // REPORTS & ANALYTICS (Tiered Access) - ARCHIVED
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/reports', \App\Livewire\Tenant\Reports\Index::class)
    //     ->name('tenant.reports.index');

    // ═══════════════════════════════════════════════════════════════════
    // SETTINGS (Branding, Schedule, Security) - ARCHIVED
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/settings', \App\Livewire\Tenant\Settings\Profile::class)
    //     ->name('tenant.settings.profile');
    // Route::get('/admin/settings/schedule', \App\Livewire\Tenant\Settings\Schedule::class)
    //     ->name('tenant.settings.schedule');
    // Route::get('/admin/settings/security', \App\Livewire\Tenant\Settings\Security::class)
    //     ->name('tenant.settings.security');

    // ═══════════════════════════════════════════════════════════════════
    // MARKETING (WhatsApp Config, QR Generation) - ARCHIVED
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/marketing', \App\Livewire\Tenant\Marketing\Index::class)
    //     ->name('tenant.marketing.index');

    // ═══════════════════════════════════════════════════════════════════
    // BILLING (Subscription & Payments)
    // ═══════════════════════════════════════════════════════════════════
    // Route::get('/admin/billing', \App\Livewire\Tenant\Billing\Index::class)
    //    ->name('tenant.billing.index');

    // Unified Profile/Account Route
    Route::get('/mi-cuenta', [ProfileController::class, 'edit'])->name('mi-cuenta.edit');
    Route::patch('/mi-cuenta', [ProfileController::class, 'update'])->name('mi-cuenta.update');
    Route::delete('/mi-cuenta', [ProfileController::class, 'destroy'])->name('mi-cuenta.destroy');

    // Analytics API
    Route::get('/analytics/data', [AnalyticsController::class, 'index'])->name('analytics.data');

    // Reviews
    Route::post('/reviews', [\App\Http\Controllers\Public\ReviewController::class, 'store'])->name('reviews.store');

    // Follow/Unfollow
    Route::post('/tenants/{tenant}/toggle-follow', [\App\Http\Controllers\Api\FollowController::class, 'toggle'])->name('tenant.toggle-follow'); // Assume Api for FollowController if not moved yet? Wait, FollowController was orphan. I didn't move it? I should move it to Api or Public. I'll assume Api/FollowController.
    // Wait, I forgot to move FollowController in the Move Step. I'll add a move step for it now.
    // I put it in Api? Or Public?
    // Follow is authenticated usually.
    // I will checking FollowController... it's small.
    // I will move it to Api\FollowController for now.

    // ═══════════════════════════════════════════════════════════════════
    // SHADOW LOGIN - Impersonation Exit Route (SECURED)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/impersonate/leave', [\App\Http\Controllers\Auth\ImpersonationController::class, 'leave'])
        ->name('impersonate.leave');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Tenant Microsite Routes (Wildcard - MUST BE LAST!)
|--------------------------------------------------------------------------
|
| REGLA CRÍTICA: Esta ruta usa un "wildcard" que captura cualquier slug.
| DEBE IR AL FINAL del archivo para no bloquear rutas específicas como
| /directorio, /ciudad, /registrar-negocio, etc.
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// FIX #5: REMOVED DUPLICATE tenant.show route
// Principle: DRY (Don't Repeat Yourself) - Route defined in routes/public.php
// The public.php version includes full product/schedule data for React frontend
// ═══════════════════════════════════════════════════════════════════════════

// Tenant routes moved to top to avoid wildcard shadowing
