<?php

use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WelcomeController;
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
// 3. REGISTRO DE NEGOCIOS (Mantiene Livewire por ahora - Fase 2)
// ═══════════════════════════════════════════════════════════════════════
Route::get('/registrar-negocio', \App\Livewire\Pages\RegisterTenant::class)->name('tenant.register');

// Página de Pago Pendiente (PRO Post-Registro)
Route::get('/registro/pago-pendiente/{payment}', \App\Livewire\Pages\PaymentPending::class)->name('payment.pending');

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
// PAYMENT STATUS - React/Inertia
// ═══════════════════════════════════════════════════════════════════════
Route::get('/payment-pending/{payment}', function ($paymentId) {
    // Fetch payment from database (simplified, adjust to your Payment model)
    return \Inertia\Inertia::render('Public/PaymentPending', [
        'payment' => [
            'id' => $paymentId,
            'status' => 'pending',
            'amount' => 499,
            'method' => 'Transferencia',
        ],
        'tenant' => null,
        'checkUrl' => "/api/payments/{$paymentId}/status",
    ]);
})->name('payment.pending');

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

    // ═══════════════════════════════════════════════════════════════════
    // REPORTS & ANALYTICS (Tiered Access)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/reports', \App\Livewire\Tenant\Reports\Index::class)
        ->name('tenant.reports.index');

    // ═══════════════════════════════════════════════════════════════════
    // SETTINGS (Branding, Schedule, Security)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/settings', \App\Livewire\Tenant\Settings\Profile::class)
        ->name('tenant.settings.profile');
    Route::get('/admin/settings/schedule', \App\Livewire\Tenant\Settings\Schedule::class)
        ->name('tenant.settings.schedule');
    Route::get('/admin/settings/security', \App\Livewire\Tenant\Settings\Security::class)
        ->name('tenant.settings.security');

    // ═══════════════════════════════════════════════════════════════════
    // MARKETING (WhatsApp Config, QR Generation)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/marketing', \App\Livewire\Tenant\Marketing\Index::class)
        ->name('tenant.marketing.index');

    // ═══════════════════════════════════════════════════════════════════
    // BILLING (Subscription & Payments)
    // ═══════════════════════════════════════════════════════════════════
    Route::get('/admin/billing', \App\Livewire\Tenant\Billing\Index::class)
        ->name('tenant.billing.index');

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

// ═══════════════════════════════════════════════════════════════════════════
// FIX #5: REMOVED DUPLICATE tenant.show route
// Principle: DRY (Don't Repeat Yourself) - Route defined in routes/public.php
// The public.php version includes full product/schedule data for React frontend
// ═══════════════════════════════════════════════════════════════════════════

require __DIR__.'/tenant.php';