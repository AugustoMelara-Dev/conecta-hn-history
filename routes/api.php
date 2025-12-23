<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CouponController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATED USER PROFILE
// ═══════════════════════════════════════════════════════════════════════════
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ═══════════════════════════════════════════════════════════════════════════
// AUTHENTICATION: OTP Routes (Public - No protection needed)
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('otp')->group(function () {
    Route::post('/generate', [OtpController::class, 'generate']);
    Route::post('/verify', [OtpController::class, 'verify']);
});

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC ANALYTICS: Conversion event tracking (No tenant protection - public)
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/interactions', [InteractionController::class, 'store']);

Route::post('/analytics/track', [\App\Http\Controllers\Api\AnalyticsController::class, 'track'])
    ->middleware('throttle:60,1');  // Rate limit: 60 requests per minute

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC ENDPOINTS: Coupon verification and listing (tenant_id in request body)
// These are called from the public storefront, no auth required
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('coupons')->group(function () {
    // Public: Verify coupon code (tenant_id comes in request body, controller validates)
    Route::post('/verify', [CouponController::class, 'verify']);
    
    // Public: List available coupons for tenant (tenant_id in query param)
    Route::get('/', [CouponController::class, 'index']);
});

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC CHECKOUT: Order creation from storefront (No auth, tenant_id in body)
// ═══════════════════════════════════════════════════════════════════════════
Route::post('/orders', [OrderController::class, 'store']);

// ═══════════════════════════════════════════════════════════════════════════
// ⚠️ PROTECTED TENANT ROUTES: Require auth + tenant ownership verification
// ═══════════════════════════════════════════════════════════════════════════
// These routes handle sensitive tenant-specific resources.
// The tenant.ownership middleware validates that route-model-bound resources
// belong to the authenticated user's tenant BEFORE reaching the controller.
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth:sanctum', 'tenant.ownership'])->group(function () {
    
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
    // Future protected endpoints can be added here:
    // - Products CRUD
    // - Customers management
    // - Analytics exports
    // ─────────────────────────────────────────────────────────────────────────
});
