<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
// Assumed Event exists or will be created
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TenantMicrositeController - Clean Read-Only
 *
 * ARCHITECTURE:
 * - Pure Read: No side-effects (Event Driven Views).
 * - Logic-Free: Queries moved to Repository.
 * - Gatekeeper: Visibility rules in Service.
 */
final class TenantMicrositeController extends Controller
{
    public function __construct(
        private readonly TenantStorefrontRepository $repository,
        private readonly TenantMicrositeTransformer $transformer,
        private readonly PlanFeatureFlagService $planService,
        private readonly TenantVisibilityService $visibilityService
    ) {}

    public function show(Request $request): Response
    {
        // 1. Resolve Tenant from Container (set by IdentifyTenantBySubdomain middleware)
        $tenant = app('current_tenant');

        if (! $tenant) {
            abort(404, 'Business not found');
        }

        $tenant = $this->repository->findBySlug($tenant->slug) ?? $tenant;

        // 2. Gatekeeper Logic (Domain Service)
        $this->visibilityService->check($tenant);

        // 3. Side-Effect Managment (Event Driven)
        // Fire and Forget. Listener handles increment logic.
        // Needs basic Event class.
        if (class_exists(\App\Events\Analytics\MicrositeViewed::class)) {
            \App\Events\Analytics\MicrositeViewed::dispatch($tenant, $request->ip(), $request->userAgent());
        }

        // 4. Transform & Respond
        $totalProductsCount = Schema::hasTable('products')
            ? $tenant->products()->where('is_visible', true)->count()
            : 0;
        $productLimit = $this->planService->getMaxProductLimit($tenant);

        $tenantData = $this->transformer->transform($tenant);
        $visibleProductsCount = count($tenantData['products']);

        $isOwner = $request->user() && $request->user()->id === $tenant->user_id;

        return Inertia::render('Tenant/Show', [
            'tenantPlan' => $this->planService->determinePlan($tenant),
            'tenant' => $tenantData,
            'products' => $tenantData['products'],
            'hasHiddenProducts' => $totalProductsCount > $visibleProductsCount,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => max(0, $totalProductsCount - $visibleProductsCount),
            'productLimit' => $productLimit,
            'isOwner' => $isOwner,
            'isFollowing' => false,
            'followersCount' => 0,
            'isOpen' => $this->transformer->isCurrentlyOpen($tenant),
            'openingHours' => $this->transformer->transformOpeningHours($tenant->relationLoaded('openingHours') ? $tenant->openingHours : collect()),
            'seoStructuredData' => $this->transformer->buildStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', $tenant),
        ]);
    }
}
