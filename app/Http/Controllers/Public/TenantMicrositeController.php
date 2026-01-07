<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Transformers\TenantMicrositeTransformer;
use App\Domain\TenantGovernance\Models\Tenant;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TenantMicrositeController - PUBLIC READ-ONLY Controller
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CQRS LITE: This controller handles READS only (public storefront)      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Responsibilities:                                                        ║
 * ║  1. Route model binding (slug → Tenant)                                   ║
 * ║  2. Visibility gatekeeper (active + subscription check)                   ║
 * ║  3. Delegate transformation to TenantMicrositeTransformer               ║
 * ║  4. Return Inertia response                                               ║
 * ║                                                                           ║
 * ║  WRITES go to: App\Http\Controllers\Tenant\TenantSettingsController      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PERFORMANCE:
 * - Eager loading via loadRelationsForDisplay()
 * - Deferred view counting (non-blocking)
 * - Cache-aside pattern for hot tenants (future)
 */
final class TenantMicrositeController extends Controller
{
    public function __construct(
        private readonly TenantMicrositeTransformer $transformer,
        private readonly PlanFeatureFlagService $planService
    ) {}

    /**
     * Display the tenant microsite / digital storefront.
     *
     * GET /{slug}
     *
     * @param Tenant $tenant Route model bound by slug
     * @return Response Inertia response with pre-processed props
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function show(Tenant $tenant): Response
    {
        // ═══════════════════════════════════════════════════════════════════
        // GATEKEEPER: Multi-layer visibility validation
        // ═══════════════════════════════════════════════════════════════════
        $this->ensureTenantIsVisible($tenant);

        // ═══════════════════════════════════════════════════════════════════
        // VIEW COUNTING: Deferred execution (non-blocking)
        // ═══════════════════════════════════════════════════════════════════
        defer(fn () => $tenant->increment('views'));

        // ═══════════════════════════════════════════════════════════════════
        // EAGER LOADING: Prevent N+1 queries
        // ═══════════════════════════════════════════════════════════════════
        $this->loadRelationsForDisplay($tenant);

        // ═══════════════════════════════════════════════════════════════════
        // SOFT GATE UX: Count before limit for upsell messaging
        // ═══════════════════════════════════════════════════════════════════
        $totalProductsCount = $tenant->products()->where('is_visible', true)->count();
        $productLimit = $this->planService->getMaxProductLimit($tenant);

        // ═══════════════════════════════════════════════════════════════════
        // TRANSFORMATION: Delegate ALL data processing to transformer
        // ═══════════════════════════════════════════════════════════════════
        $tenantData = $this->transformer->transform($tenant);
        $visibleProductsCount = count($tenantData['products']);

        // ═══════════════════════════════════════════════════════════════════
        // ROLE AWARENESS: Owner-specific UX features
        // ═══════════════════════════════════════════════════════════════════
        $isOwner = auth()->check() && auth()->id() === $tenant->user_id;

        return Inertia::render('Tenant/Show', [
            // SaaS Tier for frontend conditional rendering
            'tenantPlan' => $this->planService->determinePlan($tenant),

            // Pre-transformed tenant object (no computation in React)
            'tenant' => $tenantData,

            // Products as separate prop for component flexibility
            'products' => $tenantData['products'],

            // Soft Gate data for upsell messaging
            'hasHiddenProducts' => $totalProductsCount > $visibleProductsCount,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => max(0, $totalProductsCount - $visibleProductsCount),
            'productLimit' => $productLimit,

            // Role awareness for owner-specific actions
            'isOwner' => $isOwner,

            // Social features
            'isFollowing' => false, // TODO: Implement follows
            'followersCount' => 0,

            // Business hours (real-time calculation)
            'isOpen' => $this->transformer->isCurrentlyOpen($tenant),
            'openingHours' => $this->transformer->transformOpeningHours($tenant->openingHours),

            // SEO structured data
            'seoStructuredData' => $this->transformer->buildStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', $tenant),
        ]);
    }

    /**
     * Ensure tenant is visible to public visitors.
     *
     * SECURITY: Multi-layer validation
     * - Layer 1: Administrative approval (is_active)
     * - Layer 2: Subscription status (active or trial)
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function ensureTenantIsVisible(Tenant $tenant): void
    {
        // Layer 1: Administrative approval
        if (!$tenant->is_active) {
            abort(404, 'Este negocio no está disponible.');
        }

        // Layer 2: Subscription status (no morosos in public directory)
        if (!in_array($tenant->subscription_status, ['active', 'trial'], true)) {
            abort(404, 'Este negocio no está disponible.');
        }
    }

    /**
     * Eager load relations to prevent N+1 queries.
     *
     * PERFORMANCE: Single query for all related data.
     * SECURITY: Modifier groups only loaded for Pro+ plans.
     */
    private function loadRelationsForDisplay(Tenant $tenant): void
    {
        $productLimit = $this->planService->getMaxProductLimit($tenant);
        $canShowModifiers = $this->planService->canShowModifiers($tenant);

        $relations = [
            'city:id,name,slug',
            'category:id,name,slug,icon',
            'openingHours',
            'menuSections' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'products' => function ($query) use ($productLimit) {
                $query->where('is_visible', true)
                    ->orderBy('name')
                    ->limit($productLimit);
            },
        ];

        // Pro/Enterprise: Eager load modifier groups and options
        if ($canShowModifiers) {
            $relations['products.modifierGroups'] = fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('sort_order');
            $relations['products.modifierGroups.options'] = fn ($q) => $q
                ->where('is_available', true)
                ->orderBy('sort_order');
        }

        $tenant->load($relations);
    }
}
