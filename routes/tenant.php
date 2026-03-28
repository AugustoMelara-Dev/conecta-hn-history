<?php

/*
|--------------------------------------------------------------------------
| Tenant Routes - Inertia/React Pages
|--------------------------------------------------------------------------
|
| Routes for the authenticated tenant dashboard using Inertia.js.
| All routes require authentication and return Inertia responses.
|
| This file is a routing map only:
| - No business logic
| - No closures
| - No database queries
| All logic lives in dedicated controllers.
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
use App\Http\Middleware\RedirectLegacyWorkspaceToAppPanel;
use Illuminate\Support\Facades\Route;

// Authenticated tenant routes under /workspace to avoid collisions with Filament /app.
Route::middleware(['auth', 'verified'])->prefix('workspace')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(RedirectLegacyWorkspaceToAppPanel::class)
        ->name('tenant.dashboard');

    // Products compatibility redirects + legacy print/download support
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.products.index');
        Route::get('/create', 'create')->name('tenant.products.create');
        Route::post('/', 'store')->name('tenant.products.store');
        Route::get('/{product}/edit', 'edit')->name('tenant.products.edit');
        Route::put('/{product}', 'update')->name('tenant.products.update');
        Route::delete('/{product}', 'destroy')->name('tenant.products.destroy');
        Route::put('/{product}/toggle-visibility', 'toggleVisibility')
            ->name('tenant.products.toggle-visibility');
    });

    // Orders compatibility redirects + official print/receipt support
    Route::controller(OrderController::class)->prefix('orders')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.orders.index');
        Route::put('/{order}/status', 'updateStatus')->name('tenant.orders.update-status');
        Route::get('/{order}/print-ticket', 'printTicket')->name('orders.print-ticket');
    });

    Route::get('/orders/{order}/receipt', [OrderController::class, 'downloadReceipt'])
        ->name('receipt.download');

    // Billing compatibility redirects
    Route::controller(BillingController::class)->prefix('billing')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.billing.index');
        Route::post('/report-payment', 'reportPayment')->name('tenant.billing.report-payment');
        Route::post('/upgrade', 'upgrade')->name('tenant.billing.upgrade');
    });

    // Settings compatibility redirects
    Route::controller(SettingsController::class)->prefix('settings')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.settings.index');
        Route::post('/profile', 'updateProfile')->name('tenant.settings.update-profile');
        Route::put('/schedule', 'updateSchedule')->name('tenant.settings.update-schedule');
    });

    // Reports and PDF generation
    Route::controller(ReportController::class)->prefix('reports')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.reports.index');
        Route::get('/sales-pdf', 'salesPdf')->name('reports.sales-pdf');
        Route::get('/inventory-pdf', 'inventoryPdf')->name('reports.inventory-pdf');
    });

    // Marketing and promotions
    Route::controller(MarketingController::class)->prefix('marketing')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.marketing.index');
    });

    // Upgrade
    Route::get('/upgrade', [UpgradeController::class, 'index'])
        ->middleware(RedirectLegacyWorkspaceToAppPanel::class)
        ->name('tenant.upgrade');

    // Landing page builder
    Route::controller(LandingPageController::class)->prefix('landing-page')->group(function () {
        Route::get('/', 'index')->middleware(RedirectLegacyWorkspaceToAppPanel::class)->name('tenant.landing-page.index');
        Route::put('/', 'update')->name('tenant.landing-page.update');
    });
});
