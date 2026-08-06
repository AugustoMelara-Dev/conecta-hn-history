<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AiConversationController;
use App\Http\Controllers\Api\V1\AiFoundationHealthController;
use App\Http\Controllers\Api\V1\AiHandoffController;
use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Controllers\Api\V1\AiToolConfirmationController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogImportController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\Crm\CrmDealController;
use App\Http\Controllers\Api\V1\Crm\CrmPipelineController;
use App\Http\Controllers\Api\V1\DirectoryGuideChatController;
use App\Http\Controllers\Api\V1\InteractionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PlatformAiEvaluationController;
use App\Http\Controllers\Api\V1\PlatformAiRegistryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportDownloadController;
use App\Http\Controllers\Api\V1\SalesExportController;
use App\Http\Controllers\Api\V1\TenantAgentChatController;
use App\Http\Controllers\Api\V1\TenantAiConfigurationController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Controllers\Api\V1\TenantWorkforceController;
use App\Http\Controllers\Api\V1\Crm\ActivityController;
use App\Http\Controllers\Api\V1\Crm\ContactController;
use App\Http\Controllers\Api\V1\Crm\DealController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Http\Middleware\EnsurePlatformAiRegistryConfigured;
use App\Http\Middleware\InitializePublicAiPlatformTenantContext;
use App\Infrastructure\Identity\Http\Controllers\RegisterUserController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
    Route::post('/register', RegisterUserController::class)
        ->middleware('throttle:api')
        ->name('api.register');

    Route::post('/auth/social', [AuthController::class, 'socialLogin'])
        ->middleware('throttle:api')
        ->name('api.auth.social');

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware(['auth:sanctum', 'throttle:api'])
        ->name('api.auth.logout');

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

    Route::prefix('platform/ai/evaluations')
        ->middleware([
            StartSession::class,
            'auth:sanctum',
            EnsurePlatformAiRegistryConfigured::class,
            'throttle:api',
        ])
        ->group(function (): void {
            Route::post('/runs', [PlatformAiEvaluationController::class, 'run']);
            Route::post('/comparisons', [PlatformAiEvaluationController::class, 'comparison']);
            Route::post('/decisions', [PlatformAiEvaluationController::class, 'decide']);
            Route::post('/decisions/{decision}/revoke', [PlatformAiEvaluationController::class, 'revoke'])
                ->whereUuid('decision');
        });

    Route::get('/platform/ai/health', AiFoundationHealthController::class)
        ->middleware([
            'auth:sanctum',
            EnsurePlatformAiRegistryConfigured::class,
            'throttle:api',
        ]);

    Route::prefix('otp')->group(function () {
        Route::post('/generate', [OtpController::class, 'generate'])
            ->middleware(['throttle:api.otp-generation', 'idempotency']);

        Route::post('/verify', [OtpController::class, 'verify'])
            ->middleware(['throttle:api.verification', 'idempotency']);
    });

    Route::post('/auth/otp/generate', [OtpController::class, 'generate'])
        ->middleware(['throttle:api.otp-generation', 'idempotency'])
        ->name('api.auth.otp.generate');

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC ANALYTICS: Tenant-Aware Protection
    // ═══════════════════════════════════════════════════════════════════════════
    Route::post('/interactions', [InteractionController::class, 'store'])
        ->middleware('throttle:tenant-aware'); // REPLACED: analytics-protection -> tenant-aware

    Route::post('/analytics/track', [AnalyticsController::class, 'track'])
        ->middleware('throttle:api');

    Route::post('/analytics/conversion', [AnalyticsController::class, 'conversion'])
        ->middleware('throttle:api');

    Route::post('/directory/guide/chat', DirectoryGuideChatController::class)
        ->middleware([
            InitializePublicAiPlatformTenantContext::class,
            'throttle:api',
        ])
        ->name('api.directory.guide.chat');

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC CATALOG
    // ═══════════════════════════════════════════════════════════════════════════
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware('throttle:api');

        Route::get('/{id}', [ProductController::class, 'show'])
            ->middleware('throttle:api');
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
        Route::post('/validate-coupon', [CheckoutController::class, 'validateCoupon'])
            ->middleware('throttle:api.verification')
            ->name('checkout.validate-coupon');

        Route::post('/cart-summary', [CheckoutController::class, 'getCartSummary'])
            ->middleware('throttle:api')
            ->name('checkout.cart-summary');

        Route::post('/remove-coupon', [CheckoutController::class, 'removeCoupon'])
            ->middleware('throttle:api')
            ->name('checkout.remove-coupon');
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // PROTECTED TENANT ROUTES
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'tenant.ownership', 'throttle:api'])->group(function () {
        Route::prefix('quotes')->name('api.quotes.')->middleware('tenant.context')->group(function () {
            Route::post('/', [QuoteController::class, 'store'])->name('store');
            Route::post('/{quote}/revise', [QuoteController::class, 'revise'])->name('revise');
            Route::post('/{quote}/transition', [QuoteController::class, 'transition'])->name('transition');
        });

        Route::prefix('orders')->middleware('tenant.context')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
            Route::delete('/{order}', [OrderController::class, 'destroy']);
        });

        Route::prefix('products')->middleware('tenant.context')->group(function () {
            Route::post('/', [ProductController::class, 'store'])->name('api.products.store');
            Route::match(['put', 'patch'], '/{product}', [ProductController::class, 'update'])
                ->name('api.products.update');
        });

        Route::prefix('settings')->middleware('tenant.context')->group(function () {
            Route::get('/', [TenantSettingsController::class, 'show']);
            Route::post('/update', [TenantSettingsController::class, 'update'])
                ->name('api.tenant.settings.update');
        });

        Route::post('/catalog-imports', [CatalogImportController::class, 'store'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.store');
        Route::get('/catalog-imports/{batch}', [CatalogImportController::class, 'show'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.show');
        Route::get('/catalog-imports/{batch}/items/{item}/source', [CatalogImportController::class, 'source'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.items.source');
        Route::patch('/catalog-imports/{batch}/items/{item}', [CatalogImportController::class, 'update'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.items.update');
        Route::post('/catalog-imports/{batch}/items/{item}/approve', [CatalogImportController::class, 'approve'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.items.approve');
        Route::post('/catalog-imports/{batch}/items/{item}/reject', [CatalogImportController::class, 'reject'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.items.reject');
        Route::post('/catalog-imports/{batch}/items/{item}/retry', [CatalogImportController::class, 'retry'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.items.retry');
        Route::post('/catalog-imports/{batch}/publish', [CatalogImportController::class, 'publish'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.publish');
        Route::post('/catalog-imports/{batch}/cancel', [CatalogImportController::class, 'cancel'])
            ->middleware('tenant.context')
            ->name('api.catalog-imports.cancel');

        Route::prefix('reports')->middleware('tenant.context')->group(function () {
            Route::get('/sales/excel', [ReportController::class, 'salesExcel'])
                ->name('reports.sales.excel');
            Route::get('/sales/pdf', [ReportController::class, 'salesPdf'])
                ->name('reports.sales.pdf');
            Route::get('/inventory/pdf', [ReportController::class, 'inventoryPdf'])
                ->name('reports.inventory.pdf');
            Route::get('/kitchen/pdf', [ReportController::class, 'kitchenPdf'])
                ->name('reports.kitchen.pdf');
        });

        Route::prefix('exports')->middleware('tenant.context')->group(function () {
            Route::post('/sales', [SalesExportController::class, 'store'])
                ->name('api.exports.sales');
        });

        Route::prefix('crm')->name('api.crm.')->middleware('tenant.context')->group(function () {
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
    Route::get('/reports/download/{path}', [ReportDownloadController::class, 'download'])
        ->where('path', '.*')
        ->name('reports.download')
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'signed']);

    Route::get('/exports/download', [SalesExportController::class, 'download'])
        ->name('api.exports.download')
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'signed']);

    // ═══════════════════════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // AI TOOLS
    // ═══════════════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'throttle:api'])->prefix('ai')->group(function () {
        Route::post('/generate', [AiController::class, 'generate']);
        Route::post('/optimize-profile', [AiOptimizationController::class, 'optimizeProfile'])
            ->name('api.ai.optimize-profile');
    });

    Route::post('/tenant/agent/chat', TenantAgentChatController::class)
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'throttle:api'])
        ->name('api.tenant.agent.chat');

    Route::prefix('tenant/ai/handoffs')
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'throttle:api'])
        ->group(function (): void {
            Route::get('/', [AiHandoffController::class, 'index'])->name('api.tenant.ai.handoffs.index');
            Route::get('/{handoff}', [AiHandoffController::class, 'show'])->whereUuid('handoff')->name('api.tenant.ai.handoffs.show');
            Route::post('/{handoff}/assign', [AiHandoffController::class, 'assign'])->whereUuid('handoff')->name('api.tenant.ai.handoffs.assign');
            Route::post('/{handoff}/accept', [AiHandoffController::class, 'accept'])->whereUuid('handoff')->name('api.tenant.ai.handoffs.accept');
            Route::post('/{handoff}/resolve', [AiHandoffController::class, 'resolve'])->whereUuid('handoff')->name('api.tenant.ai.handoffs.resolve');
        });

    Route::prefix('tenant/ai')
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'throttle:api'])
        ->group(function (): void {
            Route::get('/configuration', TenantAiConfigurationController::class)
                ->name('api.tenant.ai.configuration');
            Route::get('/conversations', [AiConversationController::class, 'index'])
                ->name('api.tenant.ai.conversations.index');
            Route::get('/conversations/{conversation}', [AiConversationController::class, 'show'])
                ->whereUuid('conversation')
                ->name('api.tenant.ai.conversations.show');
            Route::get('/conversations/{conversation}/messages', [AiConversationController::class, 'messages'])
                ->whereUuid('conversation')
                ->name('api.tenant.ai.conversations.messages');
            Route::get('/conversations/{conversation}/handoff', [AiConversationController::class, 'handoff'])
                ->whereUuid('conversation')
                ->name('api.tenant.ai.conversations.handoff');
        });

    Route::post('/tenant/workforce/activate', [TenantWorkforceController::class, 'activate'])
        ->middleware(['auth:sanctum', 'tenant.ownership', \App\Http\Middleware\InitializeAuthenticatedTenantContext::class, 'throttle:api'])
        ->name('api.tenant.workforce.activate');

    Route::prefix('tenant/agent/tools')
        ->middleware(['auth:sanctum', 'tenant.ownership', 'tenant.context', 'throttle:api.ai-tools'])
        ->group(function (): void {
            Route::post('/prepare', [AiToolConfirmationController::class, 'prepare'])
                ->name('api.tenant.agent.tools.prepare');
            Route::post('/{confirmation}/confirm', [AiToolConfirmationController::class, 'confirm'])
                ->whereUuid('confirmation')
                ->name('api.tenant.agent.tools.confirm');
            Route::post('/{confirmation}/cancel', [AiToolConfirmationController::class, 'cancel'])
                ->whereUuid('confirmation')
                ->name('api.tenant.agent.tools.cancel');
        });
});

// ═══════════════════════════════════════════════════════════════════════════
// WEBHOOKS (Legacy path maintained)
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('checkout')->group(function () {
    Route::post('/validate-coupon', [CheckoutController::class, 'validateCoupon'])
        ->middleware('throttle:api.verification');

    Route::post('/cart-summary', [CheckoutController::class, 'getCartSummary'])
        ->middleware('throttle:api');

    Route::post('/remove-coupon', [CheckoutController::class, 'removeCoupon'])
        ->middleware('throttle:api');
});

Route::prefix('coupons')->group(function () {
    Route::post('/verify', [CouponController::class, 'verify'])
        ->middleware('throttle:api.verification');

    Route::get('/', [CouponController::class, 'index'])
        ->middleware('throttle:api');
});

Route::post('/webhooks/payment', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment')
    ->withoutMiddleware([PreventRequestForgery::class]);




if (file_exists(base_path('routes/api-crm.php'))) require base_path('routes/api-crm.php');
