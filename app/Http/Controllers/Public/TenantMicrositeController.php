<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
// Assumed Event exists or will be created
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

    public function show(Request $request, string $tenant, \App\Domain\Shared\Services\TenantContext $tenantContext): Response
    {
        $tenant = $this->repository->findBySlug($tenant);
        if (!$tenant) {
            abort(404);
        }

        $tenantContext->setTenant($tenant->id);

        // 2. Gatekeeper Logic (Domain Service)
        $this->visibilityService->check($tenant);

        // 3. Side-Effect Managment (Event Driven)
        // Fire and Forget. Listener handles increment logic.
        // Needs basic Event class.
        if (class_exists(\App\Events\Analytics\MicrositeViewed::class)) {
            $tenantEntity = \App\Domain\TenantGovernance\Entities\TenantEntity::fromStateArray([
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->slug,
                'is_active' => $tenant->is_active,
            ]);

            \App\Events\Analytics\MicrositeViewed::dispatch($tenantEntity, $request->ip(), $request->userAgent());
        }

        // 4. Transform & Respond
        $totalProductsCount = $tenant->products()->where('is_visible', true)->count();
        $productLimit = $this->planService->getMaxProductLimit($tenant);

        $tenantData = $this->transformer->transform($tenant);
        $visibleProductsCount = count($tenantData['products']);

        // Preserve the transformed DTO contract, then paginate the in-memory collection.
        $search = (string) $request->input('search', '');
        $productsCollection = collect($tenantData['products']);

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $productsCollection = $productsCollection
                ->filter(function (array $product) use ($needle): bool {
                    $name = mb_strtolower((string) ($product['name'] ?? ''));
                    $description = mb_strtolower((string) ($product['description'] ?? ''));

                    return str_contains($name, $needle) || str_contains($description, $needle);
                })
                ->values();
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;

        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $productsCollection->forPage($page, $perPage)->values(),
            $productsCollection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $isOwner = $request->user() && $request->user()->id === $tenant->user_id;

        return Inertia::render('Tenant/Show', [
            'tenantPlan' => $this->planService->determinePlan($tenant),
            'tenant' => $tenantData,
            'products' => $paginatedProducts,
            'filters' => [
                'search' => $search,
            ],
            'hasHiddenProducts' => $totalProductsCount > $visibleProductsCount,
            'totalProductsCount' => $totalProductsCount,
            'hiddenProductsCount' => max(0, $totalProductsCount - $visibleProductsCount),
            'productLimit' => $productLimit,
            'isOwner' => $isOwner,
            'isFollowing' => false,
            'followersCount' => 0,
            'isOpen' => $this->transformer->isCurrentlyOpen($tenant),
            'openingHours' => $this->transformer->transformOpeningHours($tenant->openingHours),
            'seoStructuredData' => $this->transformer->buildStructuredData($tenant),
            'canonicalUrl' => route('tenant.microsite.show', $tenant),
        ]);
    }
}
