<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\Crm\CrmDealController;
use App\Http\Controllers\Api\V1\Crm\CrmPipelineController;
use App\Http\Controllers\Api\V1\DirectoryGuideChatController;
use App\Http\Controllers\Api\V1\InteractionController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PlatformAiRegistryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TenantAgentChatController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Middleware\EnsurePlatformAiRegistryConfigured;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

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

    Route::post('/auth/social', [AuthController::class, 'socialLogin'])
        ->middleware('throttle:api')
        ->name('api.auth.social');

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATED USER PROFILE
    // ═══════════════════════════════════════════════════════════════════════════
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware(['auth:sanctum', 'throttle:api']);

    // ═══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION: OTP Routes
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('platform/ai/registry')
        ->middleware([
            StartSession::class,
            'auth:sanctum',
            EnsurePlatformAiRegistryConfigured::class,
            'throttle:api',
        ])
        ->group(function (): void {
            Route::get('/{resource}', [PlatformAiRegistryController::class, 'index']);
            Route::post('/drafts', [PlatformAiRegistryController::class, 'createDraft']);
            Route::post(
                '/{versionType}/{versionId}/promote',
                [PlatformAiRegistryController::class, 'promote'],
            );
            Route::post('/activations', [PlatformAiRegistryController::class, 'activate']);
            Route::post(
                '/activations/{activationId}/rollback',
                [PlatformAiRegistryController::class, 'rollback'],
            );
            Route::post(
                '/{versionType}/{versionId}/kill-switch',
                [PlatformAiRegistryController::class, 'killSwitch'],
            );
            Route::post(
                '/release-decisions/{decisionId}/revoke',
                [PlatformAiRegistryController::class, 'revokeReleaseDecision'],
            );
        });

    Route::prefix('otp')->group(function () {
        Route::post('/generate', [OtpController::class, 'generate'])
            ->middleware(['throttle:api.otp-generation', 'idempotency']);

        Route::post('/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:api.verification');
    });

    Route::post('/auth/otp/generate', [OtpController::class, 'generate'])
        ->middleware(['throttle:api.otp-generation', 'idempotency'])
        ->name('api.auth.otp.generate');

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC ANALYTICS: Tenant-Aware Protection
    // ═══════════════════════════════════════════════════════════════════════════
    Route::post('/interactions', [InteractionController::class, 'store'])
        ->middleware('throttle:tenant-aware'); // REPLACED: analytics-protection -> tenant-aware

    Route::post('/analytics/track', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'track'])
        ->middleware('throttle:api');

    Route::post('/analytics/conversion', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'conversion'])
        ->middleware('throttle:api');

    Route::post('/directory/guide/chat', DirectoryGuideChatController::class)
        ->middleware('throttle:api')
        ->name('api.directory.guide.chat');

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
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('throttle:api.transactions');

    Route::prefix('checkout')->group(function () {
        // Note: CheckoutController is Public (Storefront), not Api/V1 yet, but used in API contexts.
        // Keeping as is for now, assuming it handles JSON.
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

        Route::prefix('crm')->name('api.crm.')->group(function () {
            Route::get('/pipelines', [CrmPipelineController::class, 'index'])
                ->name('pipelines.index');
            Route::post('/pipelines', [CrmPipelineController::class, 'store'])
                ->name('pipelines.store');
            Route::patch('/pipelines/{pipeline}', [CrmPipelineController::class, 'update'])
                ->name('pipelines.update');
            Route::delete('/pipelines/{pipeline}', [CrmPipelineController::class, 'destroy'])
                ->name('pipelines.destroy');
            Route::post('/pipelines/{pipeline}/stages', [CrmPipelineController::class, 'storeStage'])
                ->name('pipelines.stages.store');
            Route::patch('/stages/{stage}', [CrmPipelineController::class, 'updateStage'])
                ->name('stages.update');
            Route::delete('/stages/{stage}', [CrmPipelineController::class, 'destroyStage'])
                ->name('stages.destroy');
            Route::get('/pipeline-summary', [CrmPipelineController::class, 'summary'])
                ->name('pipeline-summary.show');
            Route::get('/deals', [CrmDealController::class, 'index'])
                ->name('deals.index');
            Route::post('/deals', [CrmDealController::class, 'store'])
                ->name('deals.store');
            Route::post('/deals/import', [CrmDealController::class, 'import'])
                ->name('deals.import');
            Route::get('/deals/export', [CrmDealController::class, 'export'])
                ->name('deals.export');
            Route::post('/leads/{lead}/convert-to-deal', [CrmDealController::class, 'convertLead'])
                ->name('leads.convert-to-deal');
            Route::patch('/deals/{deal}', [CrmDealController::class, 'update'])
                ->name('deals.update');
            Route::patch('/deals/{deal}/stage', [CrmDealController::class, 'moveStage'])
                ->name('deals.stage.update');
            Route::delete('/deals/{deal}', [CrmDealController::class, 'destroy'])
                ->name('deals.destroy');
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

    Route::post('/tenant/agent/chat', TenantAgentChatController::class)
        ->middleware(['auth:sanctum', 'throttle:api'])
        ->name('api.tenant.agent.chat');
});

// ═══════════════════════════════════════════════════════════════════════════
// WEBHOOKS (Legacy path maintained)
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('checkout')->group(function () {
    Route::post('/validate-coupon', [\App\Http\Controllers\Public\CheckoutController::class, 'validateCoupon'])
        ->middleware('throttle:api.verification');

    Route::post('/cart-summary', [\App\Http\Controllers\Public\CheckoutController::class, 'getCartSummary'])
        ->middleware('throttle:api');

    Route::post('/remove-coupon', [\App\Http\Controllers\Public\CheckoutController::class, 'removeCoupon'])
        ->middleware('throttle:api');
});

Route::prefix('coupons')->group(function () {
    Route::post('/verify', [CouponController::class, 'verify'])
        ->middleware('throttle:api.verification');

    Route::get('/', [CouponController::class, 'index'])
        ->middleware('throttle:api');
});

Route::post('/webhooks/payment', [\App\Http\Controllers\Webhook\PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
