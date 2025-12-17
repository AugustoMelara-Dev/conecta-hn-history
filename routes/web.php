<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Routes - Marketplace Frontend
|--------------------------------------------------------------------------
|
| Rutas del frontend público usando componentes Livewire.
| Prioridad: Rutas específicas PRIMERO, wildcard (/{slug}) AL FINAL.
|
*/

// ═══════════════════════════════════════════════════════════════════════
// 1. LANDING PAGE: Página de Ventas B2B
// ═══════════════════════════════════════════════════════════════════════
Route::get('/', \App\Livewire\LandingPage::class)->name('home');

// Motor de Descubrimiento (antiguo home, ahora secundario)
Route::get('/explorar', \App\Livewire\Pages\Home::class)->name('explore');

// ═══════════════════════════════════════════════════════════════════════
// 2. DIRECTORIO: Búsqueda y Filtros
// ═══════════════════════════════════════════════════════════════════════
Route::get('/directorio', \App\Livewire\Pages\Directory::class)->name('directory');

// Ruta SEO para Ciudades (ej. /ciudad/tocoa)
Route::get('/ciudad/{city}', \App\Livewire\Pages\Directory::class)->name('city.show');

// Listado de todas las ciudades
Route::get('/ciudades', \App\Livewire\Pages\Directory::class)->name('cities.index');

// Ruta SEO para Categorías (ej. /categoria/restaurantes)
Route::get('/categoria/{category}', \App\Livewire\Pages\Directory::class)->name('category.show');

// ═══════════════════════════════════════════════════════════════════════
// 3. REGISTRO DE NEGOCIOS
// ═══════════════════════════════════════════════════════════════════════
Route::get('/registrar-negocio', \App\Livewire\Pages\RegisterTenant::class)->name('tenant.register');

// Alias for legacy route name
Route::get('/register', \App\Livewire\Pages\RegisterTenant::class)->name('register');

// Página de Pago Pendiente (PRO Post-Registro)
Route::get('/registro/pago-pendiente/{payment}', \App\Livewire\Pages\PaymentPending::class)->name('payment.pending');

// ═══════════════════════════════════════════════════════════════════════
// 4. AUTHENTICATION (Login Page - RBAC Redirect)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/login', \App\Livewire\Auth\Login::class)
    ->middleware('guest')
    ->name('login');

// ═══════════════════════════════════════════════════════════════════════
// Location API Routes
// ═══════════════════════════════════════════════════════════════════════
Route::post('/location/set', [\App\Http\Controllers\LocationController::class, 'setCity'])->name('location.set');
Route::post('/location/clear', [\App\Http\Controllers\LocationController::class, 'clearCity'])->name('location.clear');
Route::get('/api/cities', [\App\Http\Controllers\LocationController::class, 'getCities'])->name('cities.all');
Route::get('/api/search', [\App\Http\Controllers\Api\SearchController::class, 'index'])->name('api.search');

// ═══════════════════════════════════════════════════════════════════════
// SOCIAL AUTHENTICATION (OAuth)
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('auth')->group(function () {
    Route::get('/{provider}/redirect', [\App\Http\Controllers\Auth\SocialAuthController::class, 'redirect'])
        ->name('social.redirect');
    Route::get('/{provider}/callback', [\App\Http\Controllers\Auth\SocialAuthController::class, 'callback'])
        ->name('social.callback');
});

// ═══════════════════════════════════════════════════════════════════════
// TRACKING ROUTES - Analytics Middleware
// Registran clics antes de redirigir a destinos externos
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('track')->group(function () {
    Route::get('/wa/{tenant:slug}', [\App\Http\Controllers\InteractionController::class, 'whatsapp'])->name('track.whatsapp');
    Route::get('/call/{tenant:slug}', [\App\Http\Controllers\InteractionController::class, 'call'])->name('track.call');
    Route::get('/maps/{tenant:slug}', [\App\Http\Controllers\InteractionController::class, 'maps'])->name('track.maps');
});

// ═══════════════════════════════════════════════════════════════════════
// Auth Routes (Dashboard, Mi Cuenta)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // ═══════════════════════════════════════════════════════════════════
    // TENANT DASHBOARD (Owner Panel)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/app/{tenant:slug}', \App\Livewire\Tenant\Dashboard::class)
        ->name('tenant.dashboard');

    // ═══════════════════════════════════════════════════════════════════
    // UPGRADE TO PRO (Pricing Table + Payment Order)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/upgrade/{tenant:slug}', \App\Livewire\Tenant\Upgrade::class)
        ->name('tenant.upgrade');

    // ═══════════════════════════════════════════════════════════════════
    // PRODUCTS CRUD (Tenant Panel)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/products', \App\Livewire\Tenant\Products\Index::class)
        ->name('tenant.products.index');
    Route::get('/admin/products/create', \App\Livewire\Tenant\Products\Form::class)
        ->name('tenant.products.create');
    Route::get('/admin/products/{product}/edit', \App\Livewire\Tenant\Products\Form::class)
        ->name('tenant.products.edit');

    // ═══════════════════════════════════════════════════════════════════
    // ORDERS MANAGEMENT (Kanban PRO / List FREE)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/orders', \App\Livewire\Tenant\Orders\Manage::class)
        ->name('tenant.orders.manage');

    // Unified Profile/Account Route
    Route::get('/mi-cuenta', [ProfileController::class, 'edit'])->name('mi-cuenta.edit');
    Route::patch('/mi-cuenta', [ProfileController::class, 'update'])->name('mi-cuenta.update');
    Route::delete('/mi-cuenta', [ProfileController::class, 'destroy'])->name('mi-cuenta.destroy');

    // Analytics API
    Route::get('/analytics/data', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.data');

    // Reviews
    Route::post('/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');

    // Follow/Unfollow
    Route::post('/tenants/{tenant}/toggle-follow', [\App\Http\Controllers\FollowController::class, 'toggle'])->name('tenant.toggle-follow');

    // ═══════════════════════════════════════════════════════════════════
    // SHADOW LOGIN - Impersonation Exit Route
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/impersonate/leave', function () {
        $impersonatorId = session('impersonator_id');

        if ($impersonatorId) {
            session()->forget('impersonator_id');
            session()->forget('impersonator_name');

            $admin = \App\Models\User::find($impersonatorId);
            if ($admin) {
                \Illuminate\Support\Facades\Auth::login($admin);
            }
        }

        return redirect('/admin');
    })->name('impersonate.leave');
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

// ═══ TENANT MICROSITES / STOREFRONT (MUST BE LAST) ═══
Route::get('/{tenant:slug}', \App\Livewire\Tenant\Shop\Show::class)
    ->name('tenant.show');

