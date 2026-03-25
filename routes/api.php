<?php

use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\InteractionController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Auth\OtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/analytics/track', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'track'])
    ->middleware('throttle:api');

// ═══════════════════════════════════════════════════════════════════════════
// API V1 ROUTING GROUP
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('v1')->group(function () {

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC REGISTRATION
    // ═══════════════════════════════════════════════════════════════════════════
    Route::post('/register', \App\Infrastructure\Identity\Http\Controllers\RegisterUserController::class)
        ->middleware('throttle:api')
        ->name('api.register');

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATED USER PROFILE
    // ═══════════════════════════════════════════════════════════════════════════
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware(['auth:sanctum', 'throttle:api'])
        ->name('api.users.show'); // Maps to /user (profile) for now, ignoring parameter if passed, or we can add specific route

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/push-subscriptions', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'update'])
            ->name('api.push-subscriptions.update');
        Route::delete('/push-subscriptions', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'destroy'])
            ->name('api.push-subscriptions.destroy');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION: OTP Routes
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('otp')->group(function () {
        Route::post('/generate', [OtpController::class, 'generate'])
            ->middleware(['throttle:api.otp-generation', 'idempotency']);

        Route::post('/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:api.verification');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION: Social Login
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('auth')->group(function () {
        Route::post('/social', [\App\Http\Controllers\Api\V1\AuthController::class, 'socialLogin'])
            ->middleware('throttle:api');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC ANALYTICS: Tenant-Aware Protection
    // ═══════════════════════════════════════════════════════════════════════════
    Route::post('/interactions', [InteractionController::class, 'store'])
        ->middleware('throttle:tenant-aware'); // REPLACED: analytics-protection -> tenant-aware

    Route::post('/analytics/track', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'track'])
        ->middleware('throttle:api');

    Route::post('/analytics/conversion', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'conversion'])
        ->middleware('throttle:api');

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC CATALOG
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware(['throttle:api', 'cacheResponse:600']);

        Route::get('/{id}', [ProductController::class, 'show'])
            ->middleware(['throttle:api', 'cacheResponse:600']);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC COUPONS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('coupons')->group(function () {
        Route::post('/verify', [CouponController::class, 'verify'])
            ->middleware('throttle:api.verification');

        Route::get('/', [CouponController::class, 'index'])
            ->middleware('throttle:api');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC CHECKOUT
    // ═══════════════════════════════════════════════════════════════════════════
    Route::post('/orders', [\App\Http\Controllers\Public\OrderController::class, 'store'])
        ->middleware('throttle:api.transactions');

    Route::prefix('checkout')->group(function () {
        // Note: CheckoutController is Public (Storefront), not Api/V1 yet, but used in API contexts.
        // Keeping as is for now, assuming it handles JSON.
        Route::post('/hold-inventory', [\App\Http\Controllers\Public\CheckoutController::class, 'holdInventory'])
            ->name('checkout.hold-inventory');

        Route::post('/validate-coupon', [\App\Http\Controllers\Public\CheckoutController::class, 'validateCoupon'])
            ->middleware('throttle:api.verification')
            ->name('checkout.validate-coupon');

        Route::post('/cart-summary', [\App\Http\Controllers\Public\CheckoutController::class, 'getCartSummary'])
            ->middleware('throttle:api')
            ->name('checkout.cart-summary');

        Route::post('/remove-coupon', [\App\Http\Controllers\Public\CheckoutController::class, 'removeCoupon'])
            ->middleware('throttle:api')
            ->name('checkout.remove-coupon');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // PROTECTED TENANT ROUTES
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'tenant.ownership', 'throttle:api'])->group(function () {
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
            Route::delete('/{order}', [OrderController::class, 'destroy']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\TenantSettingsController::class, 'show']);
            Route::post('/update', [\App\Http\Controllers\Api\V1\TenantSettingsController::class, 'update'])
                ->name('api.tenant.settings.update');
        });

        Route::prefix('reports')->group(function () {
            Route::get('/sales/excel', [\App\Http\Controllers\Api\V1\ReportController::class, 'salesExcel'])
                ->name('reports.sales.excel');
            Route::get('/sales/pdf', [\App\Http\Controllers\Api\V1\ReportController::class, 'salesPdf'])
                ->name('reports.sales.pdf');
            Route::get('/inventory/pdf', [\App\Http\Controllers\Api\V1\ReportController::class, 'inventoryPdf'])
                ->name('reports.inventory.pdf');
            Route::get('/kitchen/pdf', [\App\Http\Controllers\Api\V1\ReportController::class, 'kitchenPdf'])
                ->name('reports.kitchen.pdf');
        });

        Route::prefix('exports')->group(function () {
            Route::post('/sales', [\App\Http\Controllers\Api\V1\SalesExportController::class, 'store'])
                ->name('api.exports.sales');
        });
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // REPORT DOWNLOADS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::get('/reports/download/{path}', [\App\Http\Controllers\Api\V1\ReportDownloadController::class, 'download'])
        ->where('path', '.*')
        ->name('reports.download')
        ->middleware('signed');

    Route::get('/exports/download', [\App\Http\Controllers\Api\V1\SalesExportController::class, 'download'])
        ->name('api.exports.download')
        ->middleware(['auth:sanctum', 'signed']);

    // ═══════════════════════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::get('/count', [\App\Http\Controllers\Api\V1\NotificationController::class, 'count']);
        Route::patch('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        Route::patch('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // AI TOOLS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('ai')->group(function () {
        Route::post('/generate', [\App\Http\Controllers\Api\V1\AiController::class, 'generate']);
        Route::post('/optimize-profile', [\App\Http\Controllers\Api\V1\AiOptimizationController::class, 'optimizeProfile'])
            ->name('api.ai.optimize-profile');
    });
});

// SPA compatibility layer:
// the current notification bell consumes /api/notifications directly.
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('notifications')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
    Route::get('/count', [\App\Http\Controllers\Api\V1\NotificationController::class, 'count']);
    Route::patch('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
    Route::patch('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
});

Route::post('/orders', [\App\Http\Controllers\Public\OrderController::class, 'store'])
    ->middleware('throttle:api.transactions');

// ═══════════════════════════════════════════════════════════════════════════
// WEBHOOKS (Legacy path maintained)
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/webhooks/payment', [\App\Http\Controllers\Webhook\PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::post('/webhooks/resend/email', [\App\Http\Controllers\Webhook\ResendWebhookController::class, 'handle'])
    ->name('webhooks.resend.email')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
