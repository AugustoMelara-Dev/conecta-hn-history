<?php

/*
|--------------------------------------------------------------------------
| Tenant Routes - Inertia/React Pages
|--------------------------------------------------------------------------
|
| Routes for the authenticated tenant dashboard using Inertia.js.
| All routes require authentication and return Inertia responses.
|
| ARCHITECTURE: This file is a PURE ROUTING MAP.
| - NO business logic
| - NO closures
| - NO database queries
| All logic lives in dedicated Controllers.
|
*/

use App\Http\Controllers\Tenant\CatalogImportWorkspaceController;
use App\Http\Controllers\Tenant\LegacyWorkspaceActionController;
use App\Http\Controllers\Tenant\OrderReceiptController;
use App\Http\Controllers\Tenant\WorkforceController;
use App\Http\Controllers\Tenant\WorkspaceRedirectController;
use App\Infrastructure\Http\Middleware\IdentifyTenantMiddleware;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED TENANT ROUTES
// PREFIX: /workspace (avoids collision with Filament /app panel)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'verified', IdentifyTenantMiddleware::class])->prefix('workspace')->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD (Home)
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/dashboard', [WorkspaceRedirectController::class, 'dashboard'])
        ->name('tenant.dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // PRODUCTS CRUD
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('products')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'products'])->name('tenant.products.index');
        Route::get('/create', [WorkspaceRedirectController::class, 'createProduct'])->name('tenant.products.create');
        Route::post('/', LegacyWorkspaceActionController::class)->name('tenant.products.store');
        Route::get('/{product}/edit', [WorkspaceRedirectController::class, 'editProduct'])->name('tenant.products.edit');
        Route::put('/{product}', LegacyWorkspaceActionController::class)->name('tenant.products.update');
        Route::delete('/{product}', LegacyWorkspaceActionController::class)->name('tenant.products.destroy');
        Route::put('/{product}/toggle-visibility', LegacyWorkspaceActionController::class)
            ->name('tenant.products.toggle-visibility');
    });

    Route::get('/catalog-imports', CatalogImportWorkspaceController::class)
        ->name('tenant.catalog-imports.index');

    Route::get('/workforce', [WorkforceController::class, 'show'])
        ->name('tenant.workforce.show');

    // ─────────────────────────────────────────────────────────────────────────
    // ORDERS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('orders')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'orders'])->name('tenant.orders.index');
        Route::put('/{order}/status', LegacyWorkspaceActionController::class)->name('tenant.orders.update-status');
        Route::get('/{order}/print-ticket', [OrderReceiptController::class, 'print'])->whereNumber('order')->name('orders.print-ticket');
    });

    // Receipt download (Order detail PDF)
    Route::get('/orders/{order}/receipt', [OrderReceiptController::class, 'receipt'])->whereNumber('order')
        ->name('receipt.download');

    // ─────────────────────────────────────────────────────────────────────────
    // BILLING & SUBSCRIPTION
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('billing')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'billing'])->name('tenant.billing.index');
        Route::post('/report-payment', LegacyWorkspaceActionController::class)->name('tenant.billing.report-payment');
        Route::post('/upgrade', LegacyWorkspaceActionController::class)->name('tenant.billing.upgrade');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS (Unified Hub)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('settings')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'settings'])->name('tenant.settings.index');
        Route::post('/profile', LegacyWorkspaceActionController::class)->name('tenant.settings.update-profile');
        Route::put('/schedule', LegacyWorkspaceActionController::class)->name('tenant.settings.update-schedule');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // REPORTS & PDF GENERATION
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'reports'])->name('tenant.reports.index');
        Route::get('/sales-pdf', LegacyWorkspaceActionController::class)->name('reports.sales-pdf');
        Route::get('/inventory-pdf', LegacyWorkspaceActionController::class)->name('reports.inventory-pdf');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MARKETING & PROMOTIONS
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('marketing')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'marketing'])->name('tenant.marketing.index');
        Route::post('/coupons', LegacyWorkspaceActionController::class)->name('tenant.coupons.store');
        Route::put('/coupons/{coupon}/toggle', LegacyWorkspaceActionController::class)->name('tenant.coupons.toggle');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // UPGRADE TO PRO
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/upgrade', [WorkspaceRedirectController::class, 'upgrade'])
        ->name('tenant.upgrade');

    // ─────────────────────────────────────────────────────────────────────────
    // LANDING PAGE BUILDER (PRO)
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('landing-page')->group(function () {
        Route::get('/', [WorkspaceRedirectController::class, 'landingPage'])->name('tenant.landing-page.index');
        Route::put('/', LegacyWorkspaceActionController::class)->name('tenant.landing-page.update');
    });
});
