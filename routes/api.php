<?php

use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\InteractionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED USER PROFILE
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:api']);

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATION: OTP Routes (Granular Rate Limiting Applied)
// ═══════════════════════════════════════════════════════════════════════════
//
// SECURITY ARCHITECTURE:
// - /generate: Uses 'api.otp-generation' limiter (3/min per IP)
//              Prevents SMS Pumping fraud
// - /verify: Uses 'api.verification' limiter (5/min per IP)
//            Prevents brute-force dictionary attacks
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('otp')->group(function () {
    // FINANCIAL FIREWALL: Strict SMS rate limit (3 per minute per IP)
    Route::post('/generate', [OtpController::class, 'generate'])
        ->middleware('throttle:api.otp-generation');

    // ANTI-ENUMERATION SHIELD: Verification rate limit (5 per minute per IP)
    Route::post('/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:api.verification');
});

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC ANALYTICS: Conversion event tracking (No tenant protection - public)
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/interactions', [InteractionController::class, 'store'])
    ->middleware('throttle:api');

Route::post('/analytics/track', [\App\Http\Controllers\Api\AnalyticsController::class, 'track'])
    ->middleware('throttle:api');  // Uses global API limiter (60/min)

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC CATALOG: Product listings with Cache-Aside optimization
// ═══════════════════════════════════════════════════════════════════════════
//
// PERFORMANCE ARCHITECTURE:
// - Cache-Aside Pattern with tenant-scoped keys
// - TTL: 60 minutes (catalog data is relatively static)
// - Invalidation: ProductObserver flushes on product changes
// - Tagged caching for atomic tenant cache clearing
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('products')->group(function () {
    // Public: Get product catalog for tenant
    Route::get('/', [ProductController::class, 'index'])
        ->middleware('throttle:api');

    // Public: Get single product details
    Route::get('/{id}', [ProductController::class, 'show'])
        ->middleware('throttle:api');
});

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC ENDPOINTS: Coupon verification and listing (Granular Protection)
// ═══════════════════════════════════════════════════════════════════════════
//
// SECURITY ARCHITECTURE:
// - /verify: Uses 'api.verification' limiter (5/min per IP)
//            Prevents coupon code enumeration attacks
// - / (list): Uses global 'api' limiter (60/min per IP)
//             Standard scraping protection
//
// PERFORMANCE ARCHITECTURE:
// - Cache-Aside Pattern with 15-minute TTL
// - CouponObserver invalidates on coupon changes
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('coupons')->group(function () {
    // ANTI-ENUMERATION SHIELD: Verification rate limit (5 per minute per IP)
    Route::post('/verify', [CouponController::class, 'verify'])
        ->middleware('throttle:api.verification');

    // Standard API rate limit for listing
    Route::get('/', [CouponController::class, 'index'])
        ->middleware('throttle:api');
});

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC CHECKOUT: Order creation from storefront (Write Protection)
// ═══════════════════════════════════════════════════════════════════════════
//
// SECURITY ARCHITECTURE:
// Uses 'api.transactions' limiter (10/min per IP)
// Prevents database flooding and resource exhaustion attacks
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware('throttle:api.transactions');

// ═══════════════════════════════════════════════════════════════════════════
// ⚠️ PROTECTED TENANT ROUTES: Require auth + tenant ownership verification
// ═══════════════════════════════════════════════════════════════════════════
// These routes handle sensitive tenant-specific resources.
// The tenant.ownership middleware validates that route-model-bound resources
// belong to the authenticated user's tenant BEFORE reaching the controller.
//
// RATE LIMITING: Global 'api' limiter applied (60/min per user/IP)
// Lower priority since authenticated users are lower risk than anonymous.
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth:sanctum', 'tenant.ownership', 'throttle:api'])->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // ORDERS MANAGEMENT: Admin operations on orders
    // ─────────────────────────────────────────────────────────────────────────
    Route::prefix('orders')->group(function () {
        // List tenant's orders (for dashboard/kanban)
        Route::get('/', [OrderController::class, 'index']);

        // Get specific order details
        Route::get('/{order}', [OrderController::class, 'show']);

        // Update order status (Kanban drag-drop)
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);

        // Cancel order
        Route::delete('/{order}', [OrderController::class, 'destroy']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // TENANT SETTINGS: Self-service business configuration
    // ─────────────────────────────────────────────────────────────────────────
    // SECURITY: Tenant ID is derived from authenticated user, not request body.
    // This prevents IDOR attacks where a malicious user could update another
    // tenant's banking details by manipulating the ID parameter.
    Route::prefix('settings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TenantSettingsController::class, 'show']);
        Route::patch('/', [\App\Http\Controllers\Api\TenantSettingsController::class, 'update']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Future protected endpoints can be added here:
    // - Products CRUD
    // - Customers management
    // - Analytics exports
    // ─────────────────────────────────────────────────────────────────────────
});

// ═══════════════════════════════════════════════════════════════════════════
// PAYMENT WEBHOOKS (Zero-Trust Security) - HIBERNATED
// ═══════════════════════════════════════════════════════════════════════════
// External payment providers send asynchronous notifications here.
// SECURITY:
// - CSRF automatically bypassed (API routes don't use web middleware)
// - HMAC-SHA256 signature verification in controller
// - No auth required (webhook uses cryptographic verification instead)
//
// TESTING:
// curl -X POST http://your-domain/api/webhooks/payment \
//   -H "Content-Type: application/json" \
//   -H "X-Webhook-Signature: [hmac_signature]" \
//   -d '{"event":"payment.succeeded","data":{"order_number":"ORD-123","amount":100.00}}'
// ═══════════════════════════════════════════════════════════════════════════
//
// TODO: Uncomment this route only when migrating to automated payment gateways
//       (Stripe, PayPal, PixelPay). For Honduras manual workflow, this is dormant.
//
// Route::post('/webhooks/payment', [\App\Http\Controllers\Webhook\PaymentWebhookController::class, 'handle'])
//     ->name('webhooks.payment');
