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

use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\LandingPageController;
use App\Http\Controllers\Tenant\MarketingController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\UpgradeController;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED TENANT ROUTES
// PREFIX: /workspace (avoids collision with Filament /app panel)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'verified'])->prefix('workspace')->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD (Home)
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('tenant.dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // PRODUCTS CRUD
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index')->name('tenant.products.index');
        Route::get('/create', 'create')->name('tenant.products.create');
        Route::post('/', 'store')->name('tenant.products.store');
        Route::get('/{product}/edit', 'edit')->name('tenant.products.edit');
        Route::put('/{product}', 'update')->name('tenant.products.update');
        Route::delete('/{product}', 'destroy')->name('tenant.products.destroy');
        Route::put('/{product}/toggle-visibility', 'toggleVisibility')
            ->name('tenant.products.toggle-visibility');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // ORDERS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(OrderController::class)->prefix('orders')->group(function () {
        Route::get('/', 'index')->name('tenant.orders.index');
        Route::put('/{order}/status', 'updateStatus')->name('tenant.orders.update-status');
        Route::get('/{order}/print-ticket', 'printTicket')->name('orders.print-ticket');
    });

    // Receipt download (Order detail PDF)
    Route::get('/orders/{order}/receipt', [OrderController::class, 'downloadReceipt'])
        ->name('receipt.download');

    // ─────────────────────────────────────────────────────────────────────────
    // BILLING & SUBSCRIPTION
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(BillingController::class)->prefix('billing')->group(function () {
        Route::get('/', 'index')->name('tenant.billing.index');
        Route::post('/report-payment', 'reportPayment')->name('tenant.billing.report-payment');
        Route::post('/upgrade', 'upgrade')->name('tenant.billing.upgrade');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS (Unified Hub)
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(SettingsController::class)->prefix('settings')->group(function () {
        Route::get('/', 'index')->name('tenant.settings.index');
        Route::post('/profile', 'updateProfile')->name('tenant.settings.update-profile');
        Route::put('/schedule', 'updateSchedule')->name('tenant.settings.update-schedule');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // REPORTS & PDF GENERATION
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('/', 'index')->name('tenant.reports.index');
        Route::get('/sales-pdf', 'salesPdf')->name('reports.sales-pdf');
        Route::get('/inventory-pdf', 'inventoryPdf')->name('reports.inventory-pdf');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MARKETING & PROMOTIONS
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(MarketingController::class)->prefix('marketing')->group(function () {
        Route::get('/', 'index')->name('tenant.marketing.index');
        Route::post('/coupons', 'store')->name('tenant.coupons.store');
        Route::put('/coupons/{coupon}/toggle', 'toggle')->name('tenant.coupons.toggle');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // UPGRADE TO PRO
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/upgrade', [UpgradeController::class, 'index'])
        ->name('tenant.upgrade');

    // ─────────────────────────────────────────────────────────────────────────
    // LANDING PAGE BUILDER (PRO)
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(LandingPageController::class)->prefix('landing-page')->group(function () {
        Route::get('/', 'index')->name('tenant.landing-page.index');
        Route::put('/', 'update')->name('tenant.landing-page.update');
    });
});
