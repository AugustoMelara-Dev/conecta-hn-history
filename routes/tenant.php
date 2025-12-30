<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Tenant Routes - Inertia/React Pages
|--------------------------------------------------------------------------
|
| Routes for the authenticated tenant dashboard using Inertia.js.
| All routes require authentication and return Inertia responses.
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// CONTROLLERS (Import as needed)
// ═══════════════════════════════════════════════════════════════════════════
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\MenuSectionController;

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED TENANT ROUTES
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'verified'])->prefix('app')->group(function () {

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
    // MENU SECTIONS (Categories, PRO only)
    // ─────────────────────────────────────────────────────────────────────────
    Route::post('/menu-sections', [MenuSectionController::class, 'store'])
        ->name('tenant.menu-sections.store');

    // ─────────────────────────────────────────────────────────────────────────
    // ORDERS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(OrderController::class)->prefix('orders')->group(function () {
        Route::get('/', 'index')->name('tenant.orders.index');
        Route::put('/{order}/status', 'updateStatus')->name('tenant.orders.update-status');
        Route::get('/{order}/print-ticket', 'printTicket')->name('orders.print-ticket');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // BILLING & SUBSCRIPTION
    // Principle: Complete Route Coverage - All business flows must have endpoints
    // ─────────────────────────────────────────────────────────────────────────
    Route::controller(BillingController::class)->prefix('billing')->group(function () {
        Route::get('/', 'index')->name('tenant.billing.index');
        Route::post('/report-payment', 'reportPayment')->name('tenant.billing.report-payment');
        // FIX #1: Missing upgrade route - was causing 404 on Upgrade.jsx handleProceed()
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

    // Receipt download (Order detail PDF)
    Route::get('/orders/{order}/receipt', [OrderController::class, 'downloadReceipt'])
        ->name('receipt.download');

    // ─────────────────────────────────────────────────────────────────────────
    // MARKETING & PROMOTIONS
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('marketing')->group(function () {
        Route::get('/', fn() => \Inertia\Inertia::render('Tenant/Marketing/Index', [
            'coupons' => request()->user()?->tenant?->coupons()->orderByDesc('created_at')->get() ?? [],
            'stats' => [
                'total_coupons' => request()->user()?->tenant?->coupons()->count() ?? 0,
                'active_coupons' => request()->user()?->tenant?->coupons()->where('is_active', true)->count() ?? 0,
                'total_redemptions' => request()->user()?->tenant?->coupons()->sum('usage_count') ?? 0,
            ],
            'tenant' => request()->user()?->tenant?->only('id', 'name', 'slug', 'is_pro'),
        ]))->name('tenant.marketing.index');

        Route::post('/coupons', function (\Illuminate\Http\Request $request) {
            $tenant = $request->user()?->tenant;
            abort_unless($tenant, 403);

            $validated = $request->validate([
                'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
                'type' => ['required', 'in:percentage,fixed'],
                'value' => ['required', 'numeric', 'min:1'],
                'usage_limit' => ['nullable', 'integer', 'min:1'],
                'expires_at' => ['nullable', 'date', 'after:today'],
            ]);

            $tenant->coupons()->create([
                ...$validated,
                'is_active' => true,
                'usage_count' => 0,
            ]);

            return back()->with('success', 'Cupón creado exitosamente.');
        })->name('tenant.coupons.store');

        Route::put('/coupons/{coupon}/toggle', function (\Illuminate\Http\Request $request, int $couponId) {
            $tenant = $request->user()?->tenant;
            abort_unless($tenant, 403);

            $coupon = $tenant->coupons()->findOrFail($couponId);
            $coupon->update(['is_active' => !$coupon->is_active]);

            return back();
        })->name('tenant.coupons.toggle');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD (Home)
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant, 403);

        // Calculate onboarding progress
        $steps = [
            ['id' => 'logo', 'title' => 'Subir logo', 'completed' => (bool) $tenant->logo_path, 'href' => route('tenant.settings.index')],
            ['id' => 'product', 'title' => 'Agregar producto', 'completed' => $tenant->products()->exists(), 'href' => route('tenant.products.create')],
            ['id' => 'schedule', 'title' => 'Definir horarios', 'completed' => !empty($tenant->schedule), 'href' => route('tenant.settings.index')],
            ['id' => 'phone', 'title' => 'Agregar teléfono', 'completed' => (bool) $tenant->phone, 'href' => route('tenant.settings.index')],
        ];
        $completedCount = collect($steps)->where('completed', true)->count();
        $progress = count($steps) > 0 ? round(($completedCount / count($steps)) * 100) : 100;

        // Today's stats
        $todayOrders = $tenant->orders()->whereDate('created_at', today());
        $yesterdayRevenue = $tenant->orders()
            ->whereDate('created_at', today()->subDay())
            ->whereIn('status', ['completed', 'paid'])
            ->sum('total');

        $todayRevenue = $todayOrders->clone()->whereIn('status', ['completed', 'paid'])->sum('total');
        $revenueTrend = $yesterdayRevenue > 0 ? (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100 : 0;

        return \Inertia\Inertia::render('Tenant/Dashboard', [
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
            'onboarding' => [
                'progress' => $progress,
                'steps' => $steps,
            ],
            'stats' => [
                'today_revenue' => $todayRevenue,
                'today_orders' => $todayOrders->count(),
                'pending_orders' => $tenant->orders()->where('status', 'pending')->count(),
                'revenue_trend' => round($revenueTrend, 1),
            ],
            'latestOrders' => $tenant->orders()
                ->with('items')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at->toIso8601String(),
                ]),
        ]);
    })->name('tenant.dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // UPGRADE TO PRO
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/upgrade', function (\Illuminate\Http\Request $request) {
        $tenant = $request->user()?->tenant;

        return \Inertia\Inertia::render('Tenant/Upgrade', [
            'tenant' => $tenant?->only('id', 'name', 'slug', 'is_pro'),
            'plans' => [
                'free' => ['id' => 'free', 'name' => 'Gratis', 'description' => 'Para empezar', 'price' => 0],
                'pro' => ['id' => 'pro', 'name' => 'PRO', 'description' => 'Para crecer', 'price' => 499, 'original_price' => 799],
            ],
            'paymentMethods' => [
                ['id' => 'bank', 'type' => 'bank', 'name' => 'Depósito Bancario', 'description' => 'BAC, Ficohsa, Banpaís'],
                ['id' => 'transfer', 'type' => 'transfer', 'name' => 'Transferencia', 'description' => 'Desde cualquier banco'],
            ],
        ]);
    })->name('tenant.upgrade');

    // ─────────────────────────────────────────────────────────────────────────
    // LANDING PAGE BUILDER (PRO)
    // ─────────────────────────────────────────────────────────────────────────
    Route::get('/landing-page', function (\Illuminate\Http\Request $request) {
        $tenant = $request->user()?->tenant;

        return \Inertia\Inertia::render('Tenant/LandingPage', [
            'tenant' => $tenant?->only('id', 'name', 'slug'),
            'landingPageData' => $tenant?->landing_page_data ?? ['sections' => []],
            'isPro' => (bool) $tenant?->is_pro,
        ]);
    })->name('tenant.landing-page.index');

    Route::put('/landing-page', function (\Illuminate\Http\Request $request) {
        $tenant = $request->user()?->tenant;
        abort_unless($tenant?->is_pro, 403, 'Función disponible solo para PRO');

        $tenant->update(['landing_page_data' => ['sections' => $request->input('sections', [])]]);

        return back()->with('success', 'Landing page guardada.');
    })->name('tenant.landing-page.update');
});

/*
|--------------------------------------------------------------------------
| EXAMPLE CONTROLLER IMPLEMENTATIONS
|--------------------------------------------------------------------------
|
| Below are JSDoc-style prop contracts for each controller action.
| Copy these to your actual controllers as method comments.
|
| ┌─────────────────────────────────────────────────────────────────────────┐
| │ ProductController@index                                                  │
| ├─────────────────────────────────────────────────────────────────────────┤
| │ return Inertia::render('Tenant/Products/Index', [                       │
| │     'products' => Product::where('tenant_id', $tenant->id)              │
| │         ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
| │         ->paginate(12),                                                 │
| │     'filters' => ['search' => $request->search],                        │
| │     'limits' => [                                                       │
| │         'current_count' => Product::where('tenant_id', $tenant->id)->count(),
| │         'limit' => $tenant->is_pro ? 999 : 10,                          │
| │         'percentage' => ...,                                            │
| │         'can_create' => $count < $limit,                                │
| │     ],                                                                  │
| │     'tenant' => $tenant->only('id', 'name', 'slug'),                    │
| │ ]);                                                                     │
| └─────────────────────────────────────────────────────────────────────────┘
|
| ┌─────────────────────────────────────────────────────────────────────────┐
| │ ProductController@create / @edit                                         │
| ├─────────────────────────────────────────────────────────────────────────┤
| │ return Inertia::render('Tenant/Products/Form', [                        │
| │     'product' => $product ?? null, // null for create, object for edit  │
| │     'sections' => MenuSection::where('tenant_id', $tenant->id)->get(),  │
| │     'stockOptions' => ['in_stock' => 'Disponible', 'out_of_stock' => 'Agotado'],
| │     'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),          │
| │ ]);                                                                     │
| └─────────────────────────────────────────────────────────────────────────┘
|
| ┌─────────────────────────────────────────────────────────────────────────┐
| │ OrderController@index                                                    │
| ├─────────────────────────────────────────────────────────────────────────┤
| │ return Inertia::render('Tenant/Orders/Index', [                         │
| │     'orders' => [                                                       │
| │         'pending' => Order::status('pending')->with('items')->get(),    │
| │         'processing' => Order::status('processing')->with('items')->get(),
| │         'completed' => Order::status('completed')->with('items')->get(),│
| │         'cancelled' => Order::status('cancelled')->with('items')->get(),│
| │     ],                                                                  │
| │     'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),          │
| │ ]);                                                                     │
| └─────────────────────────────────────────────────────────────────────────┘
|
| ┌─────────────────────────────────────────────────────────────────────────┐
| │ BillingController@index                                                  │
| ├─────────────────────────────────────────────────────────────────────────┤
| │ return Inertia::render('Tenant/Billing/Index', [                        │
| │     'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),          │
| │     'subscription' => $tenant->subscription?->only('status', 'renewal_date'),
| │     'payments' => Payment::where('tenant_id', $tenant->id)              │
| │         ->latest()->get(),                                              │
| │     'bankOptions' => ['bac' => 'BAC Credomatic', 'ficohsa' => 'Ficohsa'],
| │ ]);                                                                     │
| └─────────────────────────────────────────────────────────────────────────┘
|
| ┌─────────────────────────────────────────────────────────────────────────┐
| │ SettingsController@index                                                 │
| ├─────────────────────────────────────────────────────────────────────────┤
| │ return Inertia::render('Tenant/Settings/Index', [                       │
| │     'tenant' => $tenant, // Full tenant object for profile form          │
| │     'schedule' => $tenant->schedule ?? [...defaultSchedule],            │
| │     'dayLabels' => [                                                    │
| │         'monday' => 'Lunes', 'tuesday' => 'Martes', ... 'sunday' => 'Domingo'
| │     ],                                                                  │
| │ ]);                                                                     │
| └─────────────────────────────────────────────────────────────────────────┘
|
*/
